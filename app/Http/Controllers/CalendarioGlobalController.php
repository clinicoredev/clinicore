<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\Guardia;
use App\Models\User; // Añadido para poder listar a los médicos
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarioGlobalController extends Controller
{
    public function index(Request $request)
    {
        $jefe = $request->user();
        $mes = $request->input('mes', Carbon::now()->month);
        $anio = $request->input('anio', 2026);

        // Limites matemáticos del mes solicitado
        $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
        $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();

        $streamEventos = collect();

        // 1. INGESTA DE GUARDIAS DEL MES
        $guardias = Guardia::where('especialidad_id', $jefe->especialidad_id)
            ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
            ->with('facultativo:id,name')->get();

        foreach ($guardias as $g) {
            $streamEventos->push([
                'id' => 'g_' . $g->id,
                'user_id' => $g->facultativo->id, // INYECTADO PARA EL FILTRO
                'fecha' => $g->fecha->format('Y-m-d'),
                'medico' => str_replace(['Dr. ', 'Dra. '], '', $g->facultativo->name),
                'tipo' => 'GUARDIA',
                'detalle' => $g->tipo === 'festivo_24h' ? '24h (Finde)' : '17h (Diaria)',
                'estilo' => $g->tipo === 'festivo_24h' ? 'guardia_finde' : 'guardia_diaria'
            ]);
        }

        // 2. INGESTA Y "EXPLOSIÓN" DE RANGOS DE VACACIONES
        $ausencias = Ausencia::where('especialidad_id', $jefe->especialidad_id)
            ->where('estado', 'aprobada')
            ->where(function ($query) use ($inicioMes, $finMes) {
                $query->whereBetween('fecha_inicio', [$inicioMes, $finMes])
                      ->orWhereBetween('fecha_fin', [$inicioMes, $finMes])
                      ->orWhere(fn($q) => $q->where('fecha_inicio', '<', $inicioMes)->where('fecha_fin', '>', $finMes));
            })
            ->with('solicitante:id,name')->get();

        foreach ($ausencias as $a) {
            $rango = CarbonPeriod::create(
                max($a->fecha_inicio, $inicioMes), 
                min($a->fecha_fin, $finMes)
            );

            foreach ($rango as $fecha) {
                $streamEventos->push([
                    'id' => 'a_' . $a->id . '_' . $fecha->format('d'),
                    'user_id' => $a->solicitante->id, // INYECTADO PARA EL FILTRO
                    'fecha' => $fecha->format('Y-m-d'),
                    'medico' => str_replace(['Dr. ', 'Dra. '], '', $a->solicitante->name),
                    'tipo' => 'AUSENCIA',
                    'detalle' => ucfirst(str_replace('_', ' ', $a->tipo)),
                    'estilo' => 'ausencia'
                ]);
            }
        }

        // 3. RECUPERAR MÉDICOS DEL DEPARTAMENTO PARA EL SELECTOR
        $medicos = User::where('especialidad_id', $jefe->especialidad_id)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'SuperAdmin'))
            ->get(['id', 'name']);

        return Inertia::render('CalendarioGlobal/Index', [
            'eventos' => $streamEventos,
            'medicos' => $medicos, // NUEVA PROP PASADA A VUE
            'mes_actual' => (int)$mes,
            'anio_actual' => (int)$anio
        ]);
    }
}