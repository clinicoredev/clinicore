<?php

namespace App\Http\Controllers;

use App\Exports\GuardiasExport;
use App\Models\Ausencia;
use App\Models\Guardia;
use App\Models\LimitacionGuardia;
use App\Models\Festivo; // <--- IMPORTANTE
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ResidenteGuardiaController extends Controller
{
    private function checkConflict($userId, $fechaStr) 
    {
        $fecha = Carbon::parse($fechaStr);
        
        $ausencia = Ausencia::where('user_id', $userId)
            ->where('tipo', '!=', 'rotacion_con_guardias')
            ->where('estado', 'aprobada')
            ->where('fecha_inicio', '<=', $fecha->format('Y-m-d'))
            ->where('fecha_fin', '>=', $fecha->format('Y-m-d'))
            ->exists();
        if ($ausencia) return "El residente está ausente o de vacaciones este día.";
        
        $limites = LimitacionGuardia::where('user_id', $userId)->get();
        foreach ($limites as $l) {
            if ($l->tipo === 'dia_semana' && (int)$fecha->dayOfWeekIso === (int)$l->valor) return "Entra en conflicto con una regla recurrente.";
            if ($l->tipo === 'fecha_concreta' && $fecha->format('Y-m-d') === $l->valor) return "El residente tiene un veto exacto.";
            if ($l->tipo === 'periodo') {
                $parts = explode(',', $l->valor);
                if (count($parts) === 2 && $fecha->between(Carbon::parse($parts[0])->startOfDay(), Carbon::parse($parts[1])->endOfDay())) {
                    return "Entra en conflicto con un periodo bloqueado.";
                }
            }
        }
        
        return null;
    }

    public function index(Request $request)
    {
        $usuario = $request->user();
        $mes = $request->query('mes', now()->month);
        $anio = $request->query('anio', now()->year);

        $medicos = User::where('especialidad_id', $usuario->especialidad_id)
            ->whereHas('roles', fn($query) => $query->whereIn('name', ['Residente', 'Admin de Residentes']))
            ->get();

        $idsResidentes = $medicos->pluck('id')->toArray();

        $guardias = Guardia::where('especialidad_id', $usuario->especialidad_id)
            ->whereIn('user_id', $idsResidentes)
            ->with('facultativo') 
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->orderBy('fecha')
            ->get()
            ->map(fn($g) => [
                'id' => $g->id, 'user_id' => $g->user_id,
                'facultativo' => $g->facultativo ? $g->facultativo->name : 'Desconocido', 
                'fecha' => Carbon::parse($g->fecha)->format('Y-m-d'),
                'fecha_formateada' => Carbon::parse($g->fecha)->format('d/m/Y'),
                'dia_semana' => ucfirst(Carbon::parse($g->fecha)->locale('es')->dayName),
                'tipo' => $g->tipo,
                'tipo_badge' => $g->tipo === 'festivo_24h' ? '24h (Fin de semana/Festivo)' : '17h (Diaria)',
                'es_finde' => $g->tipo === 'festivo_24h',
                'is_manual' => (bool)$g->is_manual,
                'observaciones' => $g->observaciones
            ]);

        $limitaciones = LimitacionGuardia::with('facultativo')
            ->where('especialidad_id', $usuario->especialidad_id)
            ->whereIn('user_id', $idsResidentes)
            ->get()
            ->map(function ($l) {
                $mapaDias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
                $reglaLegible = '';
                if ($l->tipo === 'dia_semana') $reglaLegible = 'Los ' . ($mapaDias[(int)$l->valor] ?? $l->valor);
                elseif ($l->tipo === 'fecha_concreta') $reglaLegible = Carbon::parse($l->valor)->format('d/m/Y');
                elseif ($l->tipo === 'periodo') {
                    $parts = explode(',', $l->valor);
                    $reglaLegible = count($parts) === 2 ? 'Del ' . Carbon::parse($parts[0])->format('d/m/Y') . ' al ' . Carbon::parse($parts[1])->format('d/m/Y') : 'Rango inválido';
                }
                return [
                    'id' => $l->id, 'medico' => $l->facultativo ? $l->facultativo->name : 'Borrado', 
                    'regla' => $reglaLegible, 'tipo_raw' => $l->tipo, 'valor' => $l->valor, 'motivo' => $l->motivo ?: 'Sin motivo'
                ];
            });

        $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
        $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();
        
        $ausenciasMes = Ausencia::where('especialidad_id', $usuario->especialidad_id)
            ->whereIn('user_id', $idsResidentes)->where('estado', 'aprobada')
            ->where(function($q) use ($inicioMes, $finMes) {
                $q->whereBetween('fecha_inicio', [$inicioMes, $finMes])
                  ->orWhereBetween('fecha_fin', [$inicioMes, $finMes])
                  ->orWhere(fn($sub) => $sub->where('fecha_inicio', '<', $inicioMes)->where('fecha_fin', '>', $finMes));
            })->with('solicitante')->get()
            ->map(fn($a) => [
                'id' => $a->id, 'medico' => $a->solicitante ? $a->solicitante->name : 'Desconocido',
                'inicio' => Carbon::parse($a->fecha_inicio)->format('Y-m-d'), 'fin' => Carbon::parse($a->fecha_fin)->format('Y-m-d'),
                'motivo' => $a->motivo ?: 'Ausencia justificada'
            ]);

        // Carga de festivos para mostrarlos en el frontend
        $festivosDelMes = Festivo::where('especialidad_id', $usuario->especialidad_id)
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->orderBy('fecha')
            ->get()
            ->map(fn($f) => [
                'id' => $f->id,
                'fecha' => Carbon::parse($f->fecha)->format('Y-m-d'),
                'descripcion' => $f->descripcion
            ]);

        $equidad = $medicos->map(function ($medico) use ($guardias) {
            $misGuardias = $guardias->where('user_id', $medico->id);
            return [
                'nombre' => str_replace(['Dr. ', 'Dra. '], '', $medico->name),
                'totales' => $misGuardias->count(),
                'findes' => $misGuardias->where('es_finde', true)->count(),
            ];
        })->sortByDesc('totales')->values();

        return inertia('Residentes/Guardias/Index', [
            'guardias' => $guardias, 'limitaciones' => $limitaciones, 'ausencias_mes' => $ausenciasMes,
            'festivos' => $festivosDelMes, // Inyectamos los festivos visualmente
            'medicos' => $medicos, 'equidad' => $equidad, 'permisos' => ['es_jefe' => $usuario->hasAnyRole(['Jefe de Servicio', 'Admin de Residentes'])],
            'mes_actual' => (int)$mes, 'anio_actual' => (int)$anio,
        ]);
    }

    public function storeLimitacion(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id', 'tipo' => 'required|in:dia_semana,fecha_concreta,periodo',
            'valor' => 'nullable|string', 'fecha_inicio' => 'nullable|date', 'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'motivo' => 'nullable|string|max:100'
        ]);

        $valorFinal = $validated['valor'];
        if ($validated['tipo'] === 'periodo') $valorFinal = $validated['fecha_inicio'] . ',' . $validated['fecha_fin'];

        LimitacionGuardia::create([
            'especialidad_id' => $request->user()->especialidad_id, 'user_id' => $validated['user_id'],
            'tipo' => $validated['tipo'], 'valor' => $valorFinal, 'motivo' => $validated['motivo']
        ]);
        return back();
    }

    public function destroyLimitacion(LimitacionGuardia $limitacion) { $limitacion->delete(); return back(); }

    public function guardarGuardiaManual(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Jefe de Servicio', 'Admin de Residentes'])) abort(403);
        $request->validate(['user_id' => 'required|exists:users,id', 'fecha' => 'required|date', 'tipo' => 'required|in:diaria_17h,festivo_24h']);
        if ($conflicto = $this->checkConflict($request->user_id, $request->fecha)) return back()->withErrors(['conflicto' => "Operación denegada: " . $conflicto]);

        $jefe = $request->user();
        Guardia::where('especialidad_id', $jefe->especialidad_id)->where('fecha', $request->fecha)->where('user_id', $request->user_id)->delete();
        Guardia::create([
            'especialidad_id' => $jefe->especialidad_id, 'user_id' => $request->user_id, 'fecha' => $request->fecha,
            'tipo' => $request->tipo, 'estado' => 'programada', 'is_manual' => true, 'observaciones' => 'Fijado manualmente'
        ]);
        return back()->with('success', 'Guardia manual consolidada.');
    }

    public function permutar(Request $request)
    {
        $request->validate(['origen_id' => 'required|exists:guardias,id', 'destino_id' => 'required|exists:guardias,id']);
        $guardia1 = Guardia::find($request->origen_id); $guardia2 = Guardia::find($request->destino_id);

        if ($guardia1->especialidad_id !== $request->user()->especialidad_id || $guardia2->especialidad_id !== $request->user()->especialidad_id) abort(403);
        if ($conflicto1 = $this->checkConflict($guardia1->user_id, $guardia2->fecha)) return back()->withErrors(['conflicto' => "Permuta denegada para {$guardia1->facultativo->name}: " . $conflicto1]);
        if ($conflicto2 = $this->checkConflict($guardia2->user_id, $guardia1->fecha)) return back()->withErrors(['conflicto' => "Permuta denegada para {$guardia2->facultativo->name}: " . $conflicto2]);

        $tempUserId = $guardia1->user_id; $guardia1->user_id = $guardia2->user_id; $guardia2->user_id = $tempUserId;
        $guardia1->is_manual = true; $guardia2->is_manual = true;
        $guardia1->save(); $guardia2->save();
        return back()->with('success', 'Permuta realizada.');
    }

    public function generarAlgoritmo(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Jefe de Servicio', 'Admin de Residentes'])) abort(403);

        $request->validate([
            'mes' => 'required|integer|between:1,12', 'anio' => 'required|integer', 
            'personas_por_dia' => 'required|integer|min:1|max:10', 'medicos_incluidos' => 'nullable|array',
            'respetar_salientes' => 'boolean', 'distancia_minima_dias' => 'nullable|integer|min:0|max:10',
            'max_guardias_mes' => 'nullable|integer|min:0', 'max_findes_mes' => 'nullable|integer|min:0',
            'max_diarias_mes' => 'nullable|integer|min:0', 'prorratear_ausencias' => 'boolean', 
            'usar_memoria_anual' => 'boolean', 'agrupacion' => 'required|string|in:ninguna,s_d,v_s,v_s_d'
        ]);
        
        $jefe = $request->user(); $mes = $request->mes; $anio = $request->anio; $personasPorDia = (int) $request->personas_por_dia;
        $respetarSalientes = $request->boolean('respetar_salientes', true);
        $distanciaMinimaDias = $request->input('distancia_minima_dias', 1);
        $maxGuardiasMes = (int) $request->input('max_guardias_mes', 0); $maxFindesMes = (int) $request->input('max_findes_mes', 0); $maxDiariasMes = (int) $request->input('max_diarias_mes', 0);
        $prorratearAusencias = $request->boolean('prorratear_ausencias', true); $usarMemoriaAnual = $request->boolean('usar_memoria_anual', true);
        $agrupacion = $request->input('agrupacion', 'ninguna');
        
        $diasDelMes = Carbon::createFromDate($anio, $mes, 1)->daysInMonth;
        
        $medicos = User::where('especialidad_id', $jefe->especialidad_id)->whereHas('roles', fn($q) => $q->whereIn('name', ['Residente', 'Admin de Residentes']))->when($request->has('medicos_incluidos'), fn($q) => $q->whereIn('id', $request->input('medicos_incluidos')))->get();
        if ($medicos->count() < $personasPorDia) return back()->withErrors(['algoritmo' => 'Imposible generar cuadrante: No hay suficientes residentes.']);

        $idsResidentes = $medicos->pluck('id')->toArray();
        $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfMonth(); $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();

        $ausencias = Ausencia::where('especialidad_id', $jefe->especialidad_id)->whereIn('user_id', $idsResidentes)->where('estado', 'aprobada')->where('tipo', '!=', 'rotacion_con_guardias')->where('fecha_inicio', '<=', $finMes)->where('fecha_fin', '>=', $inicioMes)->get();
        $limitaciones = LimitacionGuardia::where('especialidad_id', $jefe->especialidad_id)->whereIn('user_id', $idsResidentes)->get();
        $guardiasManuales = Guardia::where('especialidad_id', $jefe->especialidad_id)->whereIn('user_id', $idsResidentes)->whereMonth('fecha', $mes)->whereYear('fecha', $anio)->where('is_manual', true)->get();

        // 0. CARGA DE FESTIVOS DEL TENANT
        $festivosArr = Festivo::where('especialidad_id', $jefe->especialidad_id)->pluck('fecha')->map(fn($f) => Carbon::parse($f)->format('Y-m-d'))->toArray();
        $esFindeOFiesta = fn(Carbon $date) => $date->isWeekend() || in_array($date->format('Y-m-d'), $festivosArr);

        // 1. CÁLCULO DE CUOTAS MATEMÁTICAS JUSTAS
        $totalSlotsFinde = 0;
        $totalSlotsDiaria = 0;
        for ($d = 1; $d <= $diasDelMes; $d++) {
            $f = Carbon::createFromDate($anio, $mes, $d);
            if ($esFindeOFiesta($f) || $f->dayOfWeekIso === 5) $totalSlotsFinde += $personasPorDia;
            else $totalSlotsDiaria += $personasPorDia;
        }

        $nMedicos = max(1, $medicos->count());
        $baseFindes = floor($totalSlotsFinde / $nMedicos);
        $remFindes = $totalSlotsFinde % $nMedicos;

        $stats = [];
        foreach ($medicos as $m) {
            $diasDisponibles = $diasDelMes;
            if ($prorratearAusencias) {
                foreach($ausencias->where('user_id', $m->id) as $a) {
                    $iA = Carbon::parse($a->fecha_inicio)->max($inicioMes); $fA = Carbon::parse($a->fecha_fin)->min($finMes);
                    $diasDisponibles -= $iA->diffInDays($fA) + 1;
                }
                foreach($limitaciones->where('user_id', $m->id) as $l) {
                    if($l->tipo === 'dia_semana') $diasDisponibles -= 4; 
                    if($l->tipo === 'periodo') {
                        $parts = explode(',', $l->valor);
                        if(count($parts) === 2) {
                            $iL = Carbon::parse($parts[0])->max($inicioMes); $fL = Carbon::parse($parts[1])->min($finMes);
                            if ($iL <= $fL) $diasDisponibles -= $iL->diffInDays($fL) + 1;
                        }
                    }
                }
            }
            $stats[$m->id] = [
                'total_mes' => 0, 'findes_mes' => 0, 'diarias_mes' => 0, 'puntos_esfuerzo' => 0,
                'total_anual' => 0, 'findes_anual' => 0, 'puntos_esfuerzo_anual' => 0, 
                'dias_disponibles_mes' => max(1, $diasDisponibles), 'turnos_vsd' => 0, 'findes_distintos' => []
            ];
        }

        if ($usarMemoriaAnual) {
            $historico = Guardia::where('especialidad_id', $jefe->especialidad_id)->whereIn('user_id', $idsResidentes)->whereBetween('fecha', [Carbon::createFromDate($anio, 1, 1)->startOfDay(), $inicioMes->copy()->subDay()->endOfDay()])->get();
            foreach ($historico as $h) {
                if (isset($stats[$h->user_id])) {
                    $stats[$h->user_id]['total_anual']++;
                    $stats[$h->user_id]['puntos_esfuerzo_anual'] += ($h->tipo === 'festivo_24h' ? 2 : 1);
                    if ($h->tipo === 'festivo_24h') $stats[$h->user_id]['findes_anual']++;
                }
            }
        }

        $medicosOrdenados = $medicos->sortBy(fn($m) => $usarMemoriaAnual ? $stats[$m->id]['findes_anual'] : 0)->values();
        $limitesEquidadFinde = [];
        foreach ($medicosOrdenados as $i => $m) {
            $limitesEquidadFinde[$m->id] = $baseFindes + ($i < $remFindes ? 1 : 0);
        }

        $getFindeId = function($fechaStr) {
            $f = Carbon::parse($fechaStr);
            if ($f->dayOfWeekIso === 5) return $f->copy()->addDays(2)->format('Y-m-d');
            if ($f->dayOfWeekIso === 6) return $f->copy()->addDay()->format('Y-m-d');
            if ($f->dayOfWeekIso === 7) return $f->format('Y-m-d');
            return null;
        };

        foreach ($guardiasManuales as $gm) {
            if (!isset($stats[$gm->user_id])) continue;
            $f = Carbon::parse($gm->fecha);
            $stats[$gm->user_id]['total_mes']++;
            if ($esFindeOFiesta($f) || $gm->tipo === 'festivo_24h') {
                $stats[$gm->user_id]['findes_mes']++; $stats[$gm->user_id]['puntos_esfuerzo'] += 2;
            } else {
                $stats[$gm->user_id]['diarias_mes']++; $stats[$gm->user_id]['puntos_esfuerzo'] += 1;
            }
            if ($f->dayOfWeekIso >= 5) $stats[$gm->user_id]['turnos_vsd']++;
            
            $fId = $getFindeId($f->format('Y-m-d'));
            if ($fId && !in_array($fId, $stats[$gm->user_id]['findes_distintos'])) $stats[$gm->user_id]['findes_distintos'][] = $fId;
        }

        $guardiasAInsertar = [];

        $esElegible = function($medicoId, $fechasAAsignar) use (
            $ausencias, $limitaciones, &$stats, $guardiasManuales, $esFindeOFiesta,
            $respetarSalientes, $distanciaMinimaDias, $maxGuardiasMes, $maxFindesMes, $maxDiariasMes, &$guardiasAInsertar
        ) {
            $nFindes = 0; $nDiarias = 0;
            foreach ($fechasAAsignar as $f) { if ($esFindeOFiesta($f)) $nFindes++; else $nDiarias++; }

            if ($maxGuardiasMes > 0 && $stats[$medicoId]['total_mes'] + count($fechasAAsignar) > $maxGuardiasMes) return false;
            if ($maxFindesMes > 0 && $stats[$medicoId]['findes_mes'] + $nFindes > $maxFindesMes) return false;
            if ($maxDiariasMes > 0 && $stats[$medicoId]['diarias_mes'] + $nDiarias > $maxDiariasMes) return false;
            
            $fechasStringBloque = array_map(fn($f) => $f->format('Y-m-d'), $fechasAAsignar);

            foreach ($fechasAAsignar as $fecha) {
                if ($guardiasManuales->where('user_id', $medicoId)->filter(fn($g) => Carbon::parse($g->fecha)->format('Y-m-d') === $fecha->format('Y-m-d'))->count() > 0) return false;
                if (collect($guardiasAInsertar)->where('user_id', $medicoId)->where('fecha', $fecha->format('Y-m-d'))->count() > 0) return false;

                foreach ($ausencias as $a) {
                    if ($a->user_id == $medicoId && $a->tipo !== 'rotacion_con_guardias') {
                        $inicio = Carbon::parse($a->fecha_inicio)->startOfDay(); $fin = Carbon::parse($a->fecha_fin)->endOfDay();
                        if ($fecha->between($inicio, $fin) || $fecha->isSameDay($fin) || $fecha->isSameDay($fin->copy()->addDay())) return false;
                    }
                }

                if ($respetarSalientes) {
                    $fechasMedico = collect($guardiasAInsertar)->where('user_id', $medicoId)->pluck('fecha')
                        ->concat($guardiasManuales->where('user_id', $medicoId)->map(fn($g) => Carbon::parse($g->fecha)->format('Y-m-d')))->map(fn($f) => Carbon::parse($f));

                    foreach ($fechasMedico as $fGuardia) {
                        if (in_array($fGuardia->format('Y-m-d'), $fechasStringBloque)) continue;
                        $diff = abs($fecha->diffInDays($fGuardia));
                        if ($diff > 0 && $diff <= $distanciaMinimaDias) return false;
                    }
                }

                foreach ($limitaciones as $l) {
                    if ($l->user_id == $medicoId) {
                        if ($l->tipo === 'dia_semana' && (int)$fecha->dayOfWeekIso === (int)$l->valor) return false;
                        if ($l->tipo === 'fecha_concreta' && $fecha->format('Y-m-d') === $l->valor) return false;
                        if ($l->tipo === 'periodo') {
                            $parts = explode(',', $l->valor);
                            if (count($parts) === 2 && $fecha->between(Carbon::parse($parts[0])->startOfDay(), Carbon::parse($parts[1])->endOfDay())) return false;
                        }
                    }
                }
            }
            return true;
        };

        $getBestDoctorForBlock = function($fechasBloque, $limiteFindeFlexible) use ($medicos, $esElegible, &$stats, $limitesEquidadFinde, $usarMemoriaAnual, $esFindeOFiesta, $agrupacion, $guardiasAInsertar, $guardiasManuales, $getFindeId) {
            $candidatos = $medicos->filter(fn($m) => $esElegible($m->id, $fechasBloque));
            
            // Topear a los médicos que ya han alcanzado la equidad en el mes, salvo fallo crítico
            $primeraF = reset($fechasBloque);
            if ($primeraF && $primeraF->dayOfWeekIso >= 5 && $limiteFindeFlexible !== 999) {
                $candFiltrados = $candidatos->filter(fn($m) => $stats[$m->id]['turnos_vsd'] < $limiteFindeFlexible);
                if ($candFiltrados->isNotEmpty()) $candidatos = $candFiltrados;
            }

            if ($candidatos->isEmpty()) return null;

            $candidatosPuntuados = $candidatos->map(function($m) use ($fechasBloque, &$stats, $usarMemoriaAnual, $esFindeOFiesta, $agrupacion, $guardiasAInsertar, $guardiasManuales, $getFindeId) {
                $ratioTotal = $stats[$m->id]['puntos_esfuerzo'] / max(1, $stats[$m->id]['dias_disponibles_mes']);
                $score = $ratioTotal * 1000000;
                
                if ($usarMemoriaAnual) {
                    $score += (($stats[$m->id]['puntos_esfuerzo_anual'] / max(1, $stats[$m->id]['dias_disponibles_mes'])) * 1000);
                }

                $primera = reset($fechasBloque);
                if ($primera && $primera->dayOfWeekIso >= 5) {
                    $fId = $getFindeId($primera->format('Y-m-d'));
                    $hasV = collect($guardiasAInsertar)->where('user_id', $m->id)->contains('fecha', Carbon::parse($fId)->subDays(2)->format('Y-m-d')) || $guardiasManuales->where('user_id', $m->id)->contains('fecha', Carbon::parse($fId)->subDays(2)->format('Y-m-d'));
                    $hasS = collect($guardiasAInsertar)->where('user_id', $m->id)->contains('fecha', Carbon::parse($fId)->subDays(1)->format('Y-m-d')) || $guardiasManuales->where('user_id', $m->id)->contains('fecha', Carbon::parse($fId)->subDays(1)->format('Y-m-d'));

                    if (in_array($fId, $stats[$m->id]['findes_distintos'])) {
                        $boost = false;
                        if ($agrupacion === 'v_s' && count($fechasBloque) === 1 && $primera->dayOfWeekIso == 6 && $hasV) $boost = true;
                        if ($agrupacion === 's_d' && count($fechasBloque) === 1 && $primera->dayOfWeekIso == 7 && $hasS) $boost = true;
                        if ($agrupacion === 'v_s_d' && count($fechasBloque) === 1) {
                            if ($primera->dayOfWeekIso == 6 && $hasV) $boost = true;
                            if ($primera->dayOfWeekIso == 7 && ($hasV || $hasS)) $boost = true;
                        }

                        if ($boost || count($fechasBloque) > 1) {
                            $score -= 5000000; // Imán de agrupación activo
                        } else {
                            $score += 20000; // Penaliza dividir si es un finde distinto ya asignado
                        }
                    } else {
                        // Penaliza arruinar más fines de semana de los necesarios
                        $ruined = count($stats[$m->id]['findes_distintos']);
                        $score += ($ruined * 50000); 
                    }
                }
                
                return ['medico' => $m, 'score' => $score + (rand(0,9)/10)];
            });

            return $candidatosPuntuados->sortBy('score')->first()['medico'];
        };

        $asignarBloque = function($medico, $fechas, $motivo) use (&$guardiasAInsertar, &$stats, $jefe, $esFindeOFiesta, $getFindeId) {
            foreach ($fechas as $fecha) {
                $tipoTurno = $esFindeOFiesta($fecha) ? 'festivo_24h' : 'diaria_17h';
                $guardiasAInsertar[] = [
                    'especialidad_id' => $jefe->especialidad_id, 'user_id' => $medico->id, 'fecha' => $fecha->format('Y-m-d'),
                    'tipo' => $tipoTurno, 'estado' => 'programada', 'is_manual' => false, 'observaciones' => $motivo, 
                    'created_at' => now(), 'updated_at' => now()
                ];
                $stats[$medico->id]['total_mes']++;
                if ($esFindeOFiesta($fecha)) {
                    $stats[$medico->id]['findes_mes']++; $stats[$medico->id]['puntos_esfuerzo'] += 2;
                } else {
                    $stats[$medico->id]['diarias_mes']++; $stats[$medico->id]['puntos_esfuerzo'] += 1;
                }

                if ($fecha->dayOfWeekIso >= 5) {
                    $stats[$medico->id]['turnos_vsd']++;
                    $fId = $getFindeId($fecha->format('Y-m-d'));
                    if ($fId && !in_array($fId, $stats[$medico->id]['findes_distintos'])) $stats[$medico->id]['findes_distintos'][] = $fId;
                }
            }
        };

        // FASE 1: ASIGNACIÓN DE BLOQUES DE FIN DE SEMANA
        if ($agrupacion !== 'ninguna') {
            $weekends = [];
            for ($d = 1; $d <= $diasDelMes; $d++) {
                $f = Carbon::createFromDate($anio, $mes, $d);
                if ($f->dayOfWeekIso === 5) $weekends[] = $f;
            }

            foreach ($weekends as $v) {
                $s = $v->copy()->addDay();
                $d = $v->copy()->addDays(2);
                
                $fechasBloque = [];
                if ($agrupacion === 'v_s_d' && $s->month == $mes && $d->month == $mes) $fechasBloque = [$v, $s, $d];
                elseif ($agrupacion === 's_d' && $s->month == $mes && $d->month == $mes) $fechasBloque = [$s, $d];
                elseif ($agrupacion === 'v_s' && $s->month == $mes) $fechasBloque = [$v, $s];

                if (count($fechasBloque) > 0) {
                    for ($p = 0; $p < $personasPorDia; $p++) {
                        $puestosLibres = true;
                        foreach ($fechasBloque as $fb) {
                            $cub = $guardiasManuales->filter(fn($g) => Carbon::parse($g->fecha)->format('Y-m-d') === $fb->format('Y-m-d'))->count() 
                                 + collect($guardiasAInsertar)->where('fecha', $fb->format('Y-m-d'))->count();
                            if ($cub >= $personasPorDia) $puestosLibres = false;
                        }

                        if ($puestosLibres) {
                            $cand = $getBestDoctorForBlock($fechasBloque, $limiteVSDEquitativo); 
                            if (!$cand) $cand = $getBestDoctorForBlock($fechasBloque, 999); 

                            if ($cand) {
                                $asignarBloque($cand, $fechasBloque, 'IA (Bloque ' . strtoupper($agrupacion) . ')');
                            } else {
                                foreach ($fechasBloque as $fb) {
                                    $candSingle = $getBestDoctorForBlock([$fb], 999);
                                    if ($candSingle) $asignarBloque($candSingle, [$fb], 'IA (Split)');
                                    else return back()->withErrors(['algoritmo' => 'COLAPSO: Imposible cubrir el día ' . $fb->format('d/m/Y')]);
                                }
                            }
                        }
                    }
                }
            }
        }

        // FASE 2: RELLENO EQUITATIVO DE DÍAS RESTANTES (Y FESTIVOS SUELTOS)
        for ($dia = 1; $dia <= $diasDelMes; $dia++) {
            $fecha = Carbon::createFromDate($anio, $mes, $dia);
            
            while (true) {
                $cubiertos = $guardiasManuales->filter(fn($g) => Carbon::parse($g->fecha)->format('Y-m-d') === $fecha->format('Y-m-d'))->count() 
                           + collect($guardiasAInsertar)->where('fecha', $fecha->format('Y-m-d'))->count();
                if ($cubiertos >= $personasPorDia) break;

                $limiteAplicar = ($fecha->dayOfWeekIso >= 5) ? $limiteVSDEquitativo : null;
                $cand = $getBestDoctorForBlock([$fecha], $limiteAplicar);
                if (!$cand) $cand = $getBestDoctorForBlock([$fecha], 999); // Fallback relax

                if ($cand) {
                    $motivo = $esFindeOFiesta($fecha) ? 'IA (Festivo/Finde)' : 'IA';
                    $asignarBloque($cand, [$fecha], $motivo);
                } else {
                    return back()->withErrors(['algoritmo' => 'COLAPSO: Imposible cubrir el día ' . $fecha->format('d/m/Y') . '. Demasiadas bajas o reglas.']);
                }
            }
        }

        DB::transaction(function() use ($jefe, $mes, $anio, $idsResidentes, $guardiasAInsertar) {
            Guardia::where('especialidad_id', $jefe->especialidad_id)->whereIn('user_id', $idsResidentes)->whereMonth('fecha', $mes)->whereYear('fecha', $anio)->where('is_manual', false)->delete();
            Guardia::insert($guardiasAInsertar);
        });

        return back()->with('success', 'Cuadrante equitativo generado en dos fases respetando festivos.');
    }

    public function borrarTodo(Request $request)
    {
        $request->validate(['mes' => 'required|integer', 'anio' => 'required|integer']);
        $jefe = $request->user();

        $idsResidentes = User::whereHas('roles', fn($q) => $q->whereIn('name', ['Residente', 'Admin de Residentes']))->pluck('id')->toArray();
        Guardia::where('especialidad_id', $jefe->especialidad_id)->whereIn('user_id', $idsResidentes)->whereMonth('fecha', $request->mes)->whereYear('fecha', $request->anio)->delete();
        return back()->with('success', 'Calendario del mes limpiado correctamente.');
    }

    public function destroy(Request $request, Guardia $guardia)
    {
        if ($guardia->especialidad_id !== $request->user()->especialidad_id) abort(403, 'Acceso denegado.');
        $guardia->delete(); return back()->with('success', 'Turno liberado correctamente.');
    }

    public function vaciarMes(Request $request)
    {
        $request->validate(['mes' => 'required|integer', 'anio' => 'required|integer']);
        $idsResidentes = User::whereHas('roles', fn($q) => $q->whereIn('name', ['Residente', 'Admin de Residentes']))->pluck('id')->toArray();
        Guardia::where('especialidad_id', $request->user()->especialidad_id)->whereIn('user_id', $idsResidentes)->whereMonth('fecha', $request->mes)->whereYear('request->anio')->delete();
        return back()->with('success', 'Calendario del mes reseteado por completo.');
    }

    public function exportarExcel(Request $request)
    {
        $mes = $request->query('mes', now()->month); $anio = $request->query('anio', now()->year);
        return Excel::download(new GuardiasExport($request->user()->especialidad_id, $mes, $anio), "Cuadrante_Residentes_{$mes}_{$anio}.xlsx");
    }

    public function exportarPdf(Request $request)
    {
        $mes = $request->query('mes', now()->month); $anio = $request->query('anio', now()->year);
        $usuario = $request->user();
        $idsResidentes = User::whereHas('roles', fn($q) => $q->whereIn('name', ['Residente', 'Admin de Residentes']))->pluck('id')->toArray();
        $guardias = Guardia::where('especialidad_id', $usuario->especialidad_id)->whereIn('user_id', $idsResidentes)->with('facultativo')->whereMonth('fecha', $mes)->whereYear('fecha', $anio)->orderBy('fecha')->get();

        $pdf = Pdf::loadView('reportes.guardias_pdf', [
            'guardias' => $guardias, 'mes' => $mes, 'anio' => $anio,
            'hospital' => $usuario->especialidad->hospital->nombre ?? 'Hospital', 'especialidad' => $usuario->especialidad->nombre ?? 'Unidad'
        ]);
        return $pdf->download("Cuadrante_Residentes_{$mes}_{$anio}.pdf");
    }
}