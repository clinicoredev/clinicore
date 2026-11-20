<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\Guardia;
use App\Models\Festivo;
use App\Models\User;
use App\Services\GuardiaGeneratorService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarioGlobalController extends Controller
{
    public function __construct(
        private readonly GuardiaGeneratorService $generatorService
    ) {}

    public function index(Request $request)
    {
        $jefe = $request->user();
        $especialidadId = (int) $jefe->especialidad_id;
        $mes = (int) $request->input('mes', Carbon::now()->month);
        $anio = (int) $request->input('anio', Carbon::now()->year);

        $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
        $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();

        $mapaFestivos = $this->generatorService->obtenerMapaFestivos($especialidadId, $anio);
        $streamEventos = collect();

        // 1. INGESTA DE GUARDIAS DEL MES (Adjuntos y Residentes)
        $guardias = Guardia::where('especialidad_id', $especialidadId)
            ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
            ->with('facultativo.roles')->get();

        foreach ($guardias as $g) {
            if (!$g->facultativo) continue;
            $info = $this->generatorService->clasificarTipologiaGuardia($g, $mapaFestivos);
            $detalle = $info['es_festivo_especial']
                ? ('24h [ESPECIAL]' . ($info['modalidad'] === 'localizada' ? ' [Localizada]' : ''))
                : $info['etiqueta'];

            $streamEventos->push([
                'id' => 'g_' . $g->id,
                'user_id' => $g->facultativo->id, 
                'fecha' => $info['fecha_str'],
                'medico' => str_replace(['Dr. ', 'Dra. '], '', $g->facultativo->name),
                'rol' => $g->facultativo->roles->first()?->name ?? 'Facultativo',
                'tipo' => 'GUARDIA',
                'detalle' => $detalle,
                'modalidad' => $info['modalidad'],
                'etiqueta_modalidad' => $info['etiqueta_modalidad'],
                'horas' => $info['horas'],
                'horas_efectivas' => $info['horas_efectivas_computo'],
                'categoria' => $info['categoria'],
                'es_24h' => $info['es_24h'],
                'es_festivo_oficial' => $info['es_festivo_oficial'],
                'es_festivo_especial' => $info['es_festivo_especial'],
                'estilo' => $info['es_24h'] ? 'guardia_finde' : 'guardia_diaria',
            ]);
        }

        // 2. INGESTA Y "EXPLOSIÓN" DE RANGOS DE AUSENCIAS
        $ausencias = Ausencia::where('especialidad_id', $jefe->especialidad_id)
            ->where('estado', 'aprobada')
            ->where(function ($query) use ($inicioMes, $finMes) {
                $query->whereBetween('fecha_inicio', [$inicioMes, $finMes])
                      ->orWhereBetween('fecha_fin', [$inicioMes, $finMes])
                      ->orWhere(fn($q) => $q->where('fecha_inicio', '<', $inicioMes)->where('fecha_fin', '>', $finMes));
            })
            ->with('solicitante.roles')->get();

        foreach ($ausencias as $a) {
            if (!$a->solicitante) continue;
            $rango = CarbonPeriod::create(
                max($a->fecha_inicio, $inicioMes), 
                min($a->fecha_fin, $finMes)
            );

            foreach ($rango as $fecha) {
                $streamEventos->push([
                    'id' => 'a_' . $a->id . '_' . $fecha->format('d'),
                    'user_id' => $a->solicitante->id, 
                    'fecha' => $fecha->format('Y-m-d'),
                    'medico' => str_replace(['Dr. ', 'Dra. '], '', $a->solicitante->name),
                    'rol' => $a->solicitante->roles->first()?->name ?? 'Facultativo',
                    'tipo' => 'AUSENCIA',
                    'detalle' => ucfirst(str_replace('_', ' ', $a->tipo)),
                    // Diferenciamos visualmente la rotación que mantiene guardias
                    'estilo' => $a->tipo === 'rotacion_con_guardias' ? 'rotacion_guardias' : 'ausencia'
                ]);
            }
        }

        // 3. FESTIVOS DEL MES
        $festivos = Festivo::where('especialidad_id', $jefe->especialidad_id)
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->get()
            ->map(fn($f) => [
                'fecha' => Carbon::parse($f->fecha)->format('Y-m-d'),
                'descripcion' => $f->descripcion ?: 'Festivo Oficial'
            ]);

        // 4. RECUPERAR MÉDICOS DEL DEPARTAMENTO PARA EL SELECTOR
        $medicos = User::where('especialidad_id', $jefe->especialidad_id)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'SuperAdmin'))
            ->with('roles')
            ->get(['id', 'name'])
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'rol' => $m->roles->first()?->name ?? 'Facultativo'
                ];
            });

        return Inertia::render('CalendarioGlobal/Index', [
            'eventos' => $streamEventos,
            'festivos' => $festivos,
            'medicos' => $medicos, 
            'mes_actual' => $mes,
            'anio_actual' => $anio
        ]);
    }
}