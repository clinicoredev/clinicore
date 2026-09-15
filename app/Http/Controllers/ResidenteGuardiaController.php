<?php

namespace App\Http\Controllers;

use App\Exports\GuardiasExport;
use App\Models\Ausencia;
use App\Models\Guardia;
use App\Models\LimitacionGuardia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ResidenteGuardiaController extends Controller
{
    // ====================================================================
    // FUNCIÓN DE VALIDACIÓN GLOBAL DE CONFLICTOS (Ausencias y Reglas)
    // ====================================================================
    private function checkConflict($userId, $fechaStr) 
    {
        $fecha = Carbon::parse($fechaStr);
        
        // 1. Verificar Ausencias/Vacaciones aprobadas
        $ausencia = Ausencia::where('user_id', $userId)
            ->where('estado', 'aprobada')
            ->where('fecha_inicio', '<=', $fecha->format('Y-m-d'))
            ->where('fecha_fin', '>=', $fecha->format('Y-m-d'))
            ->exists();
        if ($ausencia) return "El residente está ausente, de baja o de vacaciones este día.";
        
        // 2. Verificar Reglas de Limitación
        $limites = LimitacionGuardia::where('user_id', $userId)->get();
        foreach ($limites as $l) {
            if ($l->tipo === 'dia_semana' && (int)$fecha->dayOfWeekIso === (int)$l->valor) {
                return "Entra en conflicto con una regla de día de la semana recurrente.";
            }
            if ($l->tipo === 'fecha_concreta' && $fecha->format('Y-m-d') === $l->valor) {
                return "El residente tiene un veto exacto configurado para este día.";
            }
            if ($l->tipo === 'periodo') {
                $parts = explode(',', $l->valor);
                if (count($parts) === 2 && $fecha->between(Carbon::parse($parts[0])->startOfDay(), Carbon::parse($parts[1])->endOfDay())) {
                    return "Entra en conflicto con un periodo/rango de fechas bloqueado.";
                }
            }
        }
        
        return null; // Todo en orden, puede hacer la guardia
    }

    public function index(Request $request)
    {
        $usuario = $request->user();

        $mes = $request->query('mes', now()->month);
        $anio = $request->query('anio', now()->year);

        // 1. MÉDICOS (DESPLEGABLES): Solo del Tenant actual y con rol Residente
        $medicos = User::where('especialidad_id', $usuario->especialidad_id)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', 'Residente', 'Admin de Residentes');
            })
            ->get();

        $idsResidentes = $medicos->pluck('id')->toArray();

        // 2. GUARDIAS: Aislamiento estricto por especialidad_id y IDs de Residentes
        $guardias = Guardia::where('especialidad_id', $usuario->especialidad_id)
            ->whereIn('user_id', $idsResidentes)
            ->with('facultativo') 
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->orderBy('fecha')
            ->get()
            ->map(fn($g) => [
                'id' => $g->id,
                'user_id' => $g->user_id,
                'facultativo' => $g->facultativo ? $g->facultativo->name : 'Desconocido', 
                'fecha' => Carbon::parse($g->fecha)->format('Y-m-d'),
                'fecha_formateada' => Carbon::parse($g->fecha)->format('d/m/Y'),
                'dia_semana' => ucfirst(Carbon::parse($g->fecha)->locale('es')->dayName),
                'tipo' => $g->tipo,
                'tipo_badge' => $g->tipo === 'festivo_24h' ? '24h (Fin de semana)' : '17h (Diaria)',
                'es_finde' => $g->tipo === 'festivo_24h',
                'is_manual' => (bool)$g->is_manual,
                'observaciones' => $g->observaciones
            ]);

        // 3. LIMITACIONES: Filtradas por IDs de Residentes
        $limitaciones = LimitacionGuardia::with('facultativo')
            ->where('especialidad_id', $usuario->especialidad_id)
            ->whereIn('user_id', $idsResidentes)
            ->get()
            ->map(function ($l) {
                $mapaDias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
                
                $reglaLegible = '';
                if ($l->tipo === 'dia_semana') {
                    $reglaLegible = 'Los ' . ($mapaDias[(int)$l->valor] ?? $l->valor);
                } elseif ($l->tipo === 'fecha_concreta') {
                    $reglaLegible = Carbon::parse($l->valor)->format('d/m/Y');
                } elseif ($l->tipo === 'periodo') {
                    $parts = explode(',', $l->valor);
                    $reglaLegible = count($parts) === 2 ? 'Del ' . Carbon::parse($parts[0])->format('d/m/Y') . ' al ' . Carbon::parse($parts[1])->format('d/m/Y') : 'Rango inválido';
                }

                return [
                    'id' => $l->id,
                    'medico' => $l->facultativo ? $l->facultativo->name : 'Usuario borrado', 
                    'regla' => $reglaLegible,
                    'tipo_raw' => $l->tipo,
                    'motivo' => $l->motivo ?: 'Sin motivo especificado'
                ];
            });

        // 4. CÁLCULO DE EQUIDAD (Auditoría del mes actual)
        $equidad = $medicos->map(function ($medico) use ($guardias) {
            $misGuardias = $guardias->where('user_id', $medico->id);
            
            return [
                'nombre' => str_replace(['Dr. ', 'Dra. '], '', $medico->name),
                'totales' => $misGuardias->count(),
                'findes' => $misGuardias->where('es_finde', true)->count(),
            ];
        })->sortByDesc('totales')->values();

        // 5. PERMISOS FRONTEND: Apuntamos a la nueva carpeta de Residentes
        return inertia('Residentes/Guardias/Index', [
            'guardias' => $guardias,
            'limitaciones' => $limitaciones,
            'medicos' => $medicos,
            'equidad' => $equidad,
            'permisos' => ['es_jefe' => $usuario->hasAnyRole(['Jefe de Servicio', 'Admin de Residentes'])],
            'mes_actual' => (int)$mes,
            'anio_actual' => (int)$anio,
        ]);
    }

    public function storeLimitacion(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'tipo' => 'required|in:dia_semana,fecha_concreta,periodo', // SOPORTE PARA PERIODO
            'valor' => 'nullable|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'motivo' => 'nullable|string|max:100'
        ]);

        $valorFinal = $validated['valor'];
        if ($validated['tipo'] === 'periodo') {
            $valorFinal = $validated['fecha_inicio'] . ',' . $validated['fecha_fin'];
        }

        LimitacionGuardia::create([
            'especialidad_id' => $request->user()->especialidad_id,
            'user_id' => $validated['user_id'],
            'tipo' => $validated['tipo'],
            'valor' => $valorFinal,
            'motivo' => $validated['motivo']
        ]);

        return back();
    }

    public function destroyLimitacion(LimitacionGuardia $limitacion)
    {
        $limitacion->delete();
        return back();
    }

    public function guardarGuardiaManual(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Jefe de Servicio', 'Admin de Residentes'])) abort(403);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'fecha' => 'required|date',
            'tipo' => 'required|in:diaria_17h,festivo_24h'
        ]);

        // VERIFICACIÓN DE CONFLICTOS ANTES DE GUARDAR
        if ($conflicto = $this->checkConflict($request->user_id, $request->fecha)) {
            return back()->withErrors(['conflicto' => "Operación denegada: " . $conflicto]);
        }

        $jefe = $request->user();

        Guardia::where('especialidad_id', $jefe->especialidad_id)
            ->where('fecha', $request->fecha)
            ->where('user_id', $request->user_id) // Solo borra a esta persona, permite solapamiento manual
            ->delete();

        Guardia::create([
            'especialidad_id' => $jefe->especialidad_id,
            'user_id' => $request->user_id,
            'fecha' => $request->fecha,
            'tipo' => $request->tipo,
            'estado' => 'programada',
            'is_manual' => true,
            'observaciones' => 'Fijado manualmente desde panel'
        ]);

        return back()->with('success', 'Guardia manual consolidada correctamente.');
    }

    public function permutar(Request $request)
    {
        $request->validate([
            'origen_id' => 'required|exists:guardias,id',
            'destino_id' => 'required|exists:guardias,id',
        ]);

        $guardia1 = Guardia::find($request->origen_id);
        $guardia2 = Guardia::find($request->destino_id);

        if ($guardia1->especialidad_id !== $request->user()->especialidad_id || $guardia2->especialidad_id !== $request->user()->especialidad_id) {
            abort(403);
        }

        // VERIFICACIÓN CRUZADA DE CONFLICTOS ANTES DE PERMUTAR
        if ($conflicto1 = $this->checkConflict($guardia1->user_id, $guardia2->fecha)) {
            return back()->withErrors(['conflicto' => "Permuta denegada para {$guardia1->facultativo->name}: " . $conflicto1]);
        }
        if ($conflicto2 = $this->checkConflict($guardia2->user_id, $guardia1->fecha)) {
            return back()->withErrors(['conflicto' => "Permuta denegada para {$guardia2->facultativo->name}: " . $conflicto2]);
        }

        $tempUserId = $guardia1->user_id;
        $guardia1->user_id = $guardia2->user_id;
        $guardia2->user_id = $tempUserId;
        
        $guardia1->is_manual = true;
        $guardia2->is_manual = true;

        $guardia1->save();
        $guardia2->save();

        return back()->with('success', 'Permuta realizada: Turnos intercambiados con éxito.');
    }

    public function generarAlgoritmo(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Jefe de Servicio', 'Admin de Residentes'])) abort(403);

        $request->validate([
            'mes' => 'required|integer|between:1,12', 
            'anio' => 'required|integer', 
            'personas_por_dia' => 'required|integer|min:1|max:10',
            'medicos_incluidos' => 'nullable|array',
            'respetar_salientes' => 'boolean',
            'distancia_minima_dias' => 'nullable|integer|min:1|max:5',
            'max_guardias_mes' => 'nullable|integer|min:0',
            'max_findes_mes' => 'nullable|integer|min:0',
            'usar_memoria_anual' => 'boolean'
        ]);
        
        $jefe = $request->user();
        $mes = $request->mes;
        $anio = $request->anio;
        $personasPorDia = (int) $request->personas_por_dia;

        $respetarSalientes = $request->boolean('respetar_salientes', true);
        $distanciaMinimaDias = $request->input('distancia_minima_dias', 2);
        $maxGuardiasMes = (int) $request->input('max_guardias_mes', 0);
        $maxFindesMes = (int) $request->input('max_findes_mes', 0);
        $usarMemoriaAnual = $request->boolean('usar_memoria_anual', true);
        $diasDelMes = Carbon::createFromDate($anio, $mes, 1)->daysInMonth;
        
        // 1. Cargamos médicos filtrados por el rol Residente
        $medicos = User::where('especialidad_id', $jefe->especialidad_id)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', 'Residente', 'Admin de Residentes');
            })
            ->when($request->has('medicos_incluidos'), function ($query) use ($request) {
                $query->whereIn('id', $request->input('medicos_incluidos'));
            })
            ->get();

        if ($medicos->count() < $personasPorDia) {
            return back()->withErrors(['algoritmo' => 'Imposible generar cuadrante: No hay suficientes residentes para cubrir '.$personasPorDia.' puestos simultáneos.']);
        }

        $idsResidentes = $medicos->pluck('id')->toArray();

        // 2. Cargamos ausencias y limitaciones aisladas
        $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
        $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();

        $ausencias = Ausencia::where('especialidad_id', $jefe->especialidad_id)
            ->whereIn('user_id', $idsResidentes)
            ->where('estado', 'aprobada')
            ->where('fecha_inicio', '<=', $finMes)
            ->where('fecha_fin', '>=', $inicioMes)
            ->get();

        $limitaciones = LimitacionGuardia::where('especialidad_id', $jefe->especialidad_id)
            ->whereIn('user_id', $idsResidentes)
            ->get();

        // 3. LECTURA DE GUARDIAS MANUALES
        $guardiasManuales = Guardia::where('especialidad_id', $jefe->especialidad_id)
            ->whereIn('user_id', $idsResidentes)
            ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
            ->where('is_manual', true)
            ->get();

        // 4. ESTRUCTURA DE ESTADÍSTICAS Y EQUIDAD RELATIVA
        $stats = [];
        foreach ($medicos as $m) {
            $diasDisponibles = $diasDelMes;
            foreach($ausencias->where('user_id', $m->id) as $a) {
                $inicioA = Carbon::parse($a->fecha_inicio)->max($inicioMes);
                $finA = Carbon::parse($a->fecha_fin)->min($finMes);
                $diasDisponibles -= $inicioA->diffInDays($finA) + 1;
            }
            foreach($limitaciones->where('user_id', $m->id) as $l) {
                if($l->tipo === 'dia_semana') $diasDisponibles -= 4; 
                // Añadido descuento de dias si es un periodo entero
                if($l->tipo === 'periodo') {
                    $parts = explode(',', $l->valor);
                    if(count($parts) === 2) {
                        $inicioL = Carbon::parse($parts[0])->max($inicioMes);
                        $finL = Carbon::parse($parts[1])->min($finMes);
                        if ($inicioL <= $finL) $diasDisponibles -= $inicioL->diffInDays($finL) + 1;
                    }
                }
            }

            $stats[$m->id] = [
                'total_mes' => 0, 
                'findes_mes' => 0, 
                'total_anual' => 0,
                'findes_anual' => 0,
                'puntos_esfuerzo' => 0,
                'historial_dias_semana' => [],
                'ultima_guardia_fecha' => null,
                'dias_disponibles_mes' => max(1, $diasDisponibles)
            ];
        }

        if ($usarMemoriaAnual) {
            $inicioAnio = Carbon::createFromDate($anio, 1, 1)->startOfDay();
            $historico = Guardia::where('especialidad_id', $jefe->especialidad_id)
                ->whereIn('user_id', $idsResidentes)
                ->whereBetween('fecha', [$inicioAnio, $inicioMes->copy()->subDay()->endOfDay()])
                ->get();

            foreach ($historico as $h) {
                if (isset($stats[$h->user_id])) {
                    $stats[$h->user_id]['total_anual']++;
                    $stats[$h->user_id]['puntos_esfuerzo'] += ($h->tipo === 'festivo_24h' ? 2 : 1);
                    if ($h->tipo === 'festivo_24h') $stats[$h->user_id]['findes_anual']++;
                }
            }
        }

        foreach ($guardiasManuales as $gm) {
            if (!isset($stats[$gm->user_id])) continue;

            $f = Carbon::parse($gm->fecha);
            $stats[$gm->user_id]['total_mes']++;
            $stats[$gm->user_id]['total_anual']++;
            $stats[$gm->user_id]['historial_dias_semana'][] = $f->dayOfWeekIso;
            $stats[$gm->user_id]['ultima_guardia_fecha'] = $f->copy();
            
            if ($f->isWeekend() || $gm->tipo === 'festivo_24h') {
                $stats[$gm->user_id]['findes_mes']++;
                $stats[$gm->user_id]['findes_anual']++;
                $stats[$gm->user_id]['puntos_esfuerzo'] += 2;
            } else {
                $stats[$gm->user_id]['puntos_esfuerzo'] += 1;
            }
        }

        $diasFinde = []; $diasSemana = [];
        for ($d = 1; $d <= $diasDelMes; $d++) {
            $f = Carbon::createFromDate($anio, $mes, $d);
            $f->isWeekend() ? $diasFinde[] = $f : $diasSemana[] = $f;
        }

        $guardiasAInsertar = [];

        $esElegible = function($medicoId, Carbon $fecha) use (
            $ausencias, $limitaciones, &$stats, 
            $respetarSalientes, $distanciaMinimaDias, $maxGuardiasMes, $maxFindesMes, $guardiasAInsertar
        ) {
            if ($maxGuardiasMes > 0 && $stats[$medicoId]['total_mes'] >= $maxGuardiasMes) return false;
            if ($maxFindesMes > 0 && $fecha->isWeekend() && $stats[$medicoId]['findes_mes'] >= $maxFindesMes) return false;
            
            // Bloquear si el médico ya tiene guardia ese mismo día (prevención de auto-solapamiento)
            if (collect($guardiasAInsertar)->where('user_id', $medicoId)->where('fecha', $fecha->format('Y-m-d'))->count() > 0) return false;

            foreach ($ausencias as $a) {
                if ($a->user_id == $medicoId) {
                    $inicio = Carbon::parse($a->fecha_inicio)->startOfDay();
                    $fin = Carbon::parse($a->fecha_fin)->endOfDay();
                    if ($fecha->between($inicio, $fin) || $fecha->isSameDay($fin) || $fecha->isSameDay($fin->copy()->addDay())) {
                        return false;
                    }
                }
            }

            if ($respetarSalientes && $stats[$medicoId]['ultima_guardia_fecha'] !== null) {
                if (abs($fecha->diffInDays($stats[$medicoId]['ultima_guardia_fecha'])) < $distanciaMinimaDias) return false;
            }

            foreach ($limitaciones as $l) {
                if ($l->user_id == $medicoId) {
                    if ($l->tipo === 'dia_semana' && (int)$fecha->dayOfWeekIso === (int)$l->valor) return false;
                    if ($l->tipo === 'fecha_concreta' && $fecha->format('Y-m-d') === $l->valor) return false;
                    // REGLA PARA PERIODO
                    if ($l->tipo === 'periodo') {
                        $parts = explode(',', $l->valor);
                        if (count($parts) === 2 && $fecha->between(Carbon::parse($parts[0])->startOfDay(), Carbon::parse($parts[1])->endOfDay())) {
                            return false;
                        }
                    }
                }
            }

            return true;
        };

        // --- FASE 1: FINES DE SEMANA ---
        foreach ($diasFinde as $fecha) {
            $puestosCubiertos = $guardiasManuales->where('fecha', $fecha->format('Y-m-d'))->count();

            while ($puestosCubiertos < $personasPorDia) {
                $candidatos = $medicos->filter(fn($m) => $esElegible($m->id, $fecha));

                if ($candidatos->isEmpty()) {
                    return back()->withErrors(['algoritmo' => "COLAPSO: Imposible cubrir el fin de semana del " . $fecha->format('d/m/Y')]);
                }

                $elegido = $candidatos->sortBy(function($m) use ($stats) {
                    $ratio = $stats[$m->id]['puntos_esfuerzo'] / $stats[$m->id]['dias_disponibles_mes'];
                    return ($ratio * 1000) + (rand(0, 5) / 10);
                })->first();

                $guardiasAInsertar[] = [
                    'especialidad_id' => $jefe->especialidad_id, 'user_id' => $elegido->id, 'fecha' => $fecha->format('Y-m-d'),
                    'tipo' => 'festivo_24h', 'estado' => 'programada', 'is_manual' => false, 'observaciones' => 'IA', 'created_at' => now(), 'updated_at' => now()
                ];

                $stats[$elegido->id]['findes_mes']++;
                $stats[$elegido->id]['findes_anual']++;
                $stats[$elegido->id]['total_mes']++;
                $stats[$elegido->id]['total_anual']++;
                $stats[$elegido->id]['puntos_esfuerzo'] += 2;
                $stats[$elegido->id]['historial_dias_semana'][] = $fecha->dayOfWeekIso;
                $stats[$elegido->id]['ultima_guardia_fecha'] = $fecha->copy();
                
                $puestosCubiertos++;
            }
        }

        // --- FASE 2: DÍAS DE DIARIO (17h) ---
        foreach ($diasSemana as $fecha) {
            $puestosCubiertos = $guardiasManuales->where('fecha', $fecha->format('Y-m-d'))->count();

            while ($puestosCubiertos < $personasPorDia) {
                $candidatos = $medicos->filter(fn($m) => $esElegible($m->id, $fecha));

                if ($candidatos->isEmpty()) {
                    return back()->withErrors(['algoritmo' => "COLAPSO: Hueco irresoluble el " . $fecha->format('d/m/Y')]);
                }

                $elegido = $candidatos->sortBy(function($m) use ($stats) {
                    $ratio = $stats[$m->id]['puntos_esfuerzo'] / $stats[$m->id]['dias_disponibles_mes'];
                    return ($ratio * 1000) + (rand(0, 5) / 10);
                })->first();

                $guardiasAInsertar[] = [
                    'especialidad_id' => $jefe->especialidad_id, 'user_id' => $elegido->id, 'fecha' => $fecha->format('Y-m-d'),
                    'tipo' => 'diaria_17h', 'estado' => 'programada', 'is_manual' => false, 'observaciones' => 'IA', 'created_at' => now(), 'updated_at' => now()
                ];

                $stats[$elegido->id]['total_mes']++;
                $stats[$elegido->id]['total_anual']++;
                $stats[$elegido->id]['puntos_esfuerzo'] += 1;
                $stats[$elegido->id]['historial_dias_semana'][] = $fecha->dayOfWeekIso;
                $stats[$elegido->id]['ultima_guardia_fecha'] = $fecha->copy();
                
                $puestosCubiertos++;
            }
        }

        // Transacción de guardado
        DB::transaction(function() use ($jefe, $mes, $anio, $idsResidentes, $guardiasAInsertar) {
            Guardia::where('especialidad_id', $jefe->especialidad_id)
                ->whereIn('user_id', $idsResidentes)
                ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
                ->where('is_manual', false)
                ->delete();
                
            Guardia::insert($guardiasAInsertar);
        });

        return back()->with('success', 'Cuadrante de Residentes generado con éxito.');
    }

    public function borrarTodo(Request $request)
    {
        $request->validate(['mes' => 'required|integer', 'anio' => 'required|integer']);
        $jefe = $request->user();

        // Borra solo las guardias de residentes del mes seleccionado
        $idsResidentes = User::whereHas('roles', fn($q) => $q->whereIn('name', 'Residente', 'Admin de Residentes'))->pluck('id')->toArray();
        Guardia::where('especialidad_id', $jefe->especialidad_id)
            ->whereIn('user_id', $idsResidentes)
            ->whereMonth('fecha', $request->mes)
            ->whereYear('fecha', $request->anio)
            ->delete();

        return back()->with('success', 'Calendario del mes limpiado correctamente.');
    }

    public function destroy(Request $request, Guardia $guardia)
    {
        if ($guardia->especialidad_id !== $request->user()->especialidad_id) {
            abort(403, 'Acceso denegado.');
        }

        $guardia->delete();
        return back()->with('success', 'Turno liberado correctamente.');
    }

    public function vaciarMes(Request $request)
    {
        $request->validate(['mes' => 'required|integer', 'anio' => 'required|integer']);
        $idsResidentes = User::whereHas('roles', fn($q) => $q->whereIn('name', 'Residente', 'Admin de Residentes'))->pluck('id')->toArray();

        Guardia::where('especialidad_id', $request->user()->especialidad_id)
            ->whereIn('user_id', $idsResidentes)
            ->whereMonth('fecha', $request->mes)
            ->whereYear('fecha', $request->anio)
            ->delete();

        return back()->with('success', 'Calendario del mes reseteado por completo.');
    }

    public function exportarExcel(Request $request)
    {
        $mes = $request->query('mes', now()->month);
        $anio = $request->query('anio', now()->year);
        $especialidadId = $request->user()->especialidad_id;

        return Excel::download(new GuardiasExport($especialidadId, $mes, $anio), "Cuadrante_Residentes_{$mes}_{$anio}.xlsx");
    }

    public function exportarPdf(Request $request)
    {
        $mes = $request->query('mes', now()->month);
        $anio = $request->query('anio', now()->year);
        $usuario = $request->user();

        $idsResidentes = User::whereHas('roles', fn($q) => $q->whereIn('name', 'Residente', 'Admin de Residentes'))->pluck('id')->toArray();

        $guardias = Guardia::where('especialidad_id', $usuario->especialidad_id)
            ->whereIn('user_id', $idsResidentes)
            ->with('facultativo')
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->orderBy('fecha')
            ->get();

        $pdf = Pdf::loadView('reportes.guardias_pdf', [
            'guardias' => $guardias,
            'mes' => $mes,
            'anio' => $anio,
            'hospital' => $usuario->especialidad->hospital->nombre ?? 'Hospital',
            'especialidad' => $usuario->especialidad->nombre ?? 'Unidad'
        ]);

        return $pdf->download("Cuadrante_Residentes_{$mes}_{$anio}.pdf");
    }
}