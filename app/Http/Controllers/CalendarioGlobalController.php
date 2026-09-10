<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\Guardia;
use App\Models\User;
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

        // 1. INGESTA DE GUARDIAS DEL MES (Adjuntos y Residentes)
        // Añadimos 'facultativo.roles' para poder leer el cargo de cada médico
        $guardias = Guardia::where('especialidad_id', $jefe->especialidad_id)
            ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
            ->with('facultativo.roles')->get();

        foreach ($guardias as $g) {
            $streamEventos->push([
                'id' => 'g_' . $g->id,
                'user_id' => $g->facultativo->id, 
                'fecha' => $g->fecha->format('Y-m-d'),
                'medico' => str_replace(['Dr. ', 'Dra. '], '', $g->facultativo->name),
                'rol' => $g->facultativo->roles->first()?->name ?? 'Facultativo', // INYECTADO PARA EL FRONTEND
                'tipo' => 'GUARDIA',
                'detalle' => $g->tipo === 'festivo_24h' ? '24h (Finde)' : '17h (Diaria)',
                'estilo' => $g->tipo === 'festivo_24h' ? 'guardia_finde' : 'guardia_diaria'
            ]);
        }

        // 2. INGESTA Y "EXPLOSIÓN" DE RANGOS DE VACACIONES
        // Añadimos 'solicitante.roles' al Eager Loading
        $ausencias = Ausencia::where('especialidad_id', $jefe->especialidad_id)
            ->where('estado', 'aprobada')
            ->where(function ($query) use ($inicioMes, $finMes) {
                $query->whereBetween('fecha_inicio', [$inicioMes, $finMes])
                      ->orWhereBetween('fecha_fin', [$inicioMes, $finMes])
                      ->orWhere(fn($q) => $q->where('fecha_inicio', '<', $inicioMes)->where('fecha_fin', '>', $finMes));
            })
            ->with('solicitante.roles')->get();

        foreach ($ausencias as $a) {
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
                    'rol' => $a->solicitante->roles->first()?->name ?? 'Facultativo', // INYECTADO PARA EL FRONTEND
                    'tipo' => 'AUSENCIA',
                    'detalle' => ucfirst(str_replace('_', ' ', $a->tipo)),
                    'estilo' => 'ausencia'
                ]);
            }
        }

        // 3. RECUPERAR MÉDICOS DEL DEPARTAMENTO PARA EL SELECTOR
        // Cargamos los roles y mapeamos para tener un array limpio en Vue
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
            'medicos' => $medicos, 
            'mes_actual' => (int)$mes,
            'anio_actual' => (int)$anio
        ]);
    }
}