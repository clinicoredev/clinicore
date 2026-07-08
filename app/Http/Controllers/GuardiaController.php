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

class GuardiaController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();

        $mes = $request->query('mes', now()->month);
        $anio = $request->query('anio', now()->year);

        // 1. GUARDIAS: Aislamiento estricto por especialidad_id
        $guardias = \App\Models\Guardia::where('especialidad_id', $usuario->especialidad_id)
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

        // 2. LIMITACIONES: Carga de la relación 'facultativo' y mapeo de datos legibles
        $limitaciones = LimitacionGuardia::with('facultativo')
            ->where('especialidad_id', $usuario->especialidad_id)
            ->get()
            ->map(function ($l) {
                $mapaDias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
                
                return [
                    'id' => $l->id,
                    'medico' => $l->facultativo ? $l->facultativo->name : 'Usuario borrado', 
                    'regla' => $l->tipo === 'dia_semana' 
                        ? 'Los ' . ($mapaDias[(int)$l->valor] ?? $l->valor) 
                        : Carbon::parse($l->valor)->format('d/m/Y'),
                    'motivo' => $l->motivo ?: 'Sin motivo especificado'
                ];
            });

        // 3. MÉDICOS (DESPLEGABLES): Solo del Tenant actual y sin SuperAdmins
        $medicos = User::where('especialidad_id', $usuario->especialidad_id)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'SuperAdmin'))
            ->get();

        // 4. CÁLCULO DE EQUIDAD (Auditoría del mes actual)
        $equidad = $medicos->map(function ($medico) use ($guardias) {
            // Filtramos la colección de guardias para aislar las de este médico en concreto
            $misGuardias = $guardias->where('user_id', $medico->id);
            
            return [
                'nombre' => str_replace(['Dr. ', 'Dra. '], '', $medico->name),
                'totales' => $misGuardias->count(),
                'findes' => $misGuardias->where('es_finde', true)->count(),
            ];
        })->sortByDesc('totales')->values(); // Ordenamos de más a menos guardias para ver quién trabaja más

        // 5. PERMISOS FRONTEND: El botón de generar IA solo lo ve el Jefe
        return inertia('Guardias/Index', [
            'guardias' => $guardias,
            'limitaciones' => $limitaciones,
            'medicos' => $medicos,
            'equidad' => $equidad,
            'permisos' => ['es_jefe' => $usuario->hasRole('Jefe de Servicio')],
            'mes_actual' => (int)$mes,
            'anio_actual' => (int)$anio,
        ]);
    }

    // 1. GUARDAR LIMITACIÓN DE UN MÉDICO
    public function storeLimitacion(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'tipo' => 'required|in:dia_semana,fecha_concreta',
            'valor' => 'required|string',
            'motivo' => 'nullable|string|max:100'
        ]);

        LimitacionGuardia::create([
            'especialidad_id' => $request->user()->especialidad_id,
            'user_id' => $validated['user_id'],
            'tipo' => $validated['tipo'],
            'valor' => $validated['valor'],
            'motivo' => $validated['motivo']
        ]);

        return back();
    }

    public function destroyLimitacion(LimitacionGuardia $limitacion)
    {
        $limitacion->delete();
        return back();
    }

    public function storeManual(Request $request)
    {
        $jefe = $request->user();
        if (!$jefe->hasRole('Jefe de Servicio')) abort(403);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'fecha' => 'required|date',
            'tipo' => 'required|in:diaria_17h,festivo_24h'
        ]);

        \App\Models\Guardia::create([
            'especialidad_id' => $jefe->especialidad_id,
            'user_id' => $validated['user_id'],
            'fecha' => $validated['fecha'],
            'tipo' => $validated['tipo'],
            'observaciones' => 'Asignación manual por Jefatura',
            'estado' => 'programada',
            'is_manual' => true // <--- LA CHINCHETA SAGRADA
        ]);

        return back()->with('success', 'Guardia fijada manualmente. Ya puedes generar el resto del cuadrante.');
    }

    // =========================================================================
    // EL MOTOR CSP: GENERADOR HEURÍSTICO VORAZ DE CUADRANTES
    // =========================================================================
    // =========================================================================
    // EL MOTOR CSP v3.0: HEURÍSTICO VORAZ CON EQUIDAD PONDERADA Y SALIENTES
    // =========================================================================
    public function generarAlgoritmo(Request $request)
    {
        if (!$request->user()->hasRole('Jefe de Servicio')) abort(403);

        $request->validate([
            'mes' => 'required|integer|between:1,12', 
            'anio' => 'required|integer', 
            'medicos_incluidos' => 'nullable|array'
        ]);
        
        $jefe = $request->user();
        $mes = $request->mes;
        $anio = $request->anio;

        $diasDelMes = Carbon::createFromDate($anio, $mes, 1)->daysInMonth;
        
        // 1. Cargamos médicos (EXCLUYENDO AL SUPERADMIN Y FILTRANDO POR EL CHECKBOX)
        $medicos = User::where('especialidad_id', $jefe->especialidad_id)
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'SuperAdmin');
            })
            // Filtramos por los IDs que vienen del formulario frontend
            ->when($request->has('medicos_incluidos'), function ($query) use ($request) {
                $query->whereIn('id', $request->input('medicos_incluidos'));
            })
            ->get();

        if ($medicos->count() < 2) {
            return back()->withErrors(['algoritmo' => 'Imposible generar cuadrante: Debes incluir al menos 2 médicos en el algoritmo.']);
        }

        // 2. Cargamos bases de conocimiento externas (Ausencias inclusivas)
        $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
        $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();

        $ausencias = Ausencia::where('especialidad_id', $jefe->especialidad_id)
            ->where('estado', 'aprobada')
            ->where('fecha_inicio', '<=', $finMes)
            ->where('fecha_fin', '>=', $inicioMes)
            ->get();

        $limitaciones = LimitacionGuardia::where('especialidad_id', $jefe->especialidad_id)->get();

        // 3. LECTURA DE ANCLAJES (Guardias puestas a mano por el Jefe este mes)
        $guardiasManuales = \App\Models\Guardia::where('especialidad_id', $jefe->especialidad_id)
            ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
            ->where('is_manual', true)
            ->get();

        $diasCubiertos = $guardiasManuales->pluck('fecha')->map(fn($f) => Carbon::parse($f)->format('Y-m-d'))->toArray();

        // 4. ESTRUCTURA DE ESTADÍSTICAS Y FATIGA (Con Memoria Anual y Puntos de Esfuerzo)
        $stats = [];
        foreach ($medicos as $m) {
            $stats[$m->id] = [
                'total_mes' => 0, 
                'findes_mes' => 0, 
                'total_anual' => 0,
                'findes_anual' => 0,
                'puntos_esfuerzo' => 0, // 17h = 1pto | 24h = 2ptos
                'historial_dias_semana' => [], // Registro para evitar que repitan el mismo día
                'ultima_guardia_fecha' => null // Memoria en caliente para el descanso de 24h
            ];
        }

        // 4.5 CARGA DE LA MEMORIA HISTÓRICA (Todo lo trabajado este año ANTES de este mes)
        $inicioAnio = Carbon::createFromDate($anio, 1, 1)->startOfDay();
        $historico = \App\Models\Guardia::where('especialidad_id', $jefe->especialidad_id)
            ->whereBetween('fecha', [$inicioAnio, $inicioMes->copy()->subDay()->endOfDay()])
            ->get();

        foreach ($historico as $h) {
            if (isset($stats[$h->user_id])) {
                $stats[$h->user_id]['total_anual']++;
                if ($h->tipo === 'festivo_24h') {
                    $stats[$h->user_id]['findes_anual']++;
                    $stats[$h->user_id]['puntos_esfuerzo'] += 2; // Finde vale doble
                } else {
                    $stats[$h->user_id]['puntos_esfuerzo'] += 1; // Diaria vale normal
                }
            }
        }

        // 5. INYECTAR EL CANSANCIO DE LAS GUARDIAS MANUALES DEL MES ACTUAL
        foreach ($guardiasManuales as $gm) {
            if (!isset($stats[$gm->user_id])) continue; // Si es un médico que hemos excluido en el checkbox, lo ignoramos

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

        // 6. CLASIFICACIÓN DE DÍAS LIBRES (Saltándonos las chinchetas manuales)
        $diasFinde = []; $diasSemana = [];
        for ($d = 1; $d <= $diasDelMes; $d++) {
            $f = Carbon::createFromDate($anio, $mes, $d);
            if (in_array($f->format('Y-m-d'), $diasCubiertos)) continue;
            
            $f->isWeekend() ? $diasFinde[] = $f : $diasSemana[] = $f;
        }

        $guardiasAInsertar = [];

        // FUNCIÓN EVALUADORA DE RESTRICCIONES DURAS (Filtro de exclusión)
        $esElegible = function($medicoId, Carbon $fecha) use ($ausencias, $limitaciones, &$stats) {
            // Regla 1: Vacaciones inclusivas (+ día de gracia post-vacacional)
            foreach ($ausencias as $a) {
                if ($a->user_id == $medicoId) {
                    $inicio = Carbon::parse($a->fecha_inicio)->startOfDay();
                    $fin = Carbon::parse($a->fecha_fin)->endOfDay();
                    if ($fecha->between($inicio, $fin) || $fecha->isSameDay($fin) || $fecha->isSameDay($fin->copy()->addDay())) {
                        return false;
                    }
                }
            }

            // Regla 2: Descanso mínimo obligatorio (24h de separación)
            if ($stats[$medicoId]['ultima_guardia_fecha'] !== null) {
                if (abs($fecha->diffInDays($stats[$medicoId]['ultima_guardia_fecha'])) < 2) return false;
            }

            // Regla 3: Vetos de agenda personales
            foreach ($limitaciones as $l) {
                if ($l->user_id == $medicoId) {
                    if ($l->tipo === 'dia_semana' && (int)$fecha->dayOfWeekIso === (int)$l->valor) return false;
                    if ($l->tipo === 'fecha_concreta' && $fecha->format('Y-m-d') === $l->valor) return false;
                }
            }

            // Regla 4: Variedad obligatoria (Máximo 2 guardias el mismo día de la semana, ej: no hacer 3 lunes)
            $vecesEsteDia = count(array_filter($stats[$medicoId]['historial_dias_semana'], fn($d) => $d === $fecha->dayOfWeekIso));
            if ($vecesEsteDia >= 2) return false;

            return true;
        };

        // --- FASE 1: REPARTO DE FINES DE SEMANA (Prioridad Máxima) ---
        foreach ($diasFinde as $fecha) {
            $candidatos = $medicos->filter(fn($m) => $esElegible($m->id, $fecha));

            if ($candidatos->isEmpty()) {
                return back()->withErrors(['algoritmo' => "COLAPSO: Imposible cubrir el fin de semana del " . $fecha->format('d/m/Y') . ". El equipo incumple descansos o límites."]);
            }

            // LÓGICA DE EQUIDAD ANUAL Y DE PUNTOS PARA FINES DE SEMANA
            $elegido = $candidatos->sortBy(function($m) use ($stats, $fecha, $guardiasAInsertar) {
                $cargaSemanal = collect($guardiasAInsertar)
                    ->where('user_id', $m->id)
                    ->where('fecha', '>=', $fecha->copy()->subDays(6)->format('Y-m-d'))
                    ->count();

                // Multiplicadores: Quien tenga MENOS findes anuales y MENOS puntos de esfuerzo va primero
                return ($stats[$m->id]['findes_anual'] * 1000) 
                     + ($stats[$m->id]['puntos_esfuerzo'] * 100) 
                     + ($cargaSemanal * 10) 
                     + (rand(0, 5) / 10);
            })->first();

            $guardiasAInsertar[] = [
                'especialidad_id' => $jefe->especialidad_id, 'user_id' => $elegido->id, 'fecha' => $fecha->format('Y-m-d'),
                'tipo' => 'festivo_24h', 'estado' => 'programada', 'is_manual' => false, 'observaciones' => 'IA', 'created_at' => now(), 'updated_at' => now()
            ];

            // Actualizamos los marcadores en caliente
            $stats[$elegido->id]['findes_mes']++;
            $stats[$elegido->id]['findes_anual']++;
            $stats[$elegido->id]['total_mes']++;
            $stats[$elegido->id]['total_anual']++;
            $stats[$elegido->id]['puntos_esfuerzo'] += 2; // +2 puntos por guardia de finde
            $stats[$elegido->id]['historial_dias_semana'][] = $fecha->dayOfWeekIso;
            $stats[$elegido->id]['ultima_guardia_fecha'] = $fecha->copy();
        }

        // --- FASE 2: REPARTO ENTRE SEMANA (Turnos ordinarios de 17h) ---
        foreach ($diasSemana as $fecha) {
            $candidatos = $medicos->filter(fn($m) => $esElegible($m->id, $fecha));

            if ($candidatos->isEmpty()) {
                return back()->withErrors(['algoritmo' => "COLAPSO: Hueco irresoluble el " . $fecha->format('d/m/Y') . ". No hay facultativos elegibles."]);
            }

            // LÓGICA DE EQUIDAD ANUAL Y DE PUNTOS PARA DÍAS DE DIARIO
            $elegido = $candidatos->sortBy(function($m) use ($stats, $fecha, $guardiasAInsertar) {
                $cargaSemanal = collect($guardiasAInsertar)
                    ->where('user_id', $m->id)
                    ->where('fecha', '>=', $fecha->copy()->subDays(6)->format('Y-m-d'))
                    ->count();
                
                $repiteDia = count(array_filter($stats[$m->id]['historial_dias_semana'], fn($d) => $d === $fecha->dayOfWeekIso));

                // Multiplicadores: El motor intentará nivelar SIEMPRE los puntos de esfuerzo totales.
                return ($stats[$m->id]['puntos_esfuerzo'] * 100) 
                     + ($stats[$m->id]['total_anual'] * 10) 
                     + ($cargaSemanal * 10) 
                     + ($repiteDia * 5) 
                     + (rand(0, 5) / 10);
            })->first();

            $guardiasAInsertar[] = [
                'especialidad_id' => $jefe->especialidad_id, 'user_id' => $elegido->id, 'fecha' => $fecha->format('Y-m-d'),
                'tipo' => 'diaria_17h', 'estado' => 'programada', 'is_manual' => false, 'observaciones' => 'IA', 'created_at' => now(), 'updated_at' => now()
            ];

            // Actualizamos los marcadores en caliente
            $stats[$elegido->id]['total_mes']++;
            $stats[$elegido->id]['total_anual']++;
            $stats[$elegido->id]['puntos_esfuerzo'] += 1; // +1 punto por guardia diaria
            $stats[$elegido->id]['historial_dias_semana'][] = $fecha->dayOfWeekIso;
            $stats[$elegido->id]['ultima_guardia_fecha'] = $fecha->copy();
        }

        // Reemplazo transaccional: borramos la IA antigua, protegemos las manuales y metemos la IA nueva
        \Illuminate\Support\Facades\DB::transaction(function() use ($jefe, $mes, $anio, $guardiasAInsertar) {
            \App\Models\Guardia::where('especialidad_id', $jefe->especialidad_id)
                ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
                ->where('is_manual', false)
                ->delete();
                
            \App\Models\Guardia::insert($guardiasAInsertar);
        });

        return back()->with('success', 'Cuadrante optimizado generado correctamente con equidad anual.');
    }


    public function borrarTodo(Request $request)
    {
        $request->validate([
            'mes' => 'required|integer',
            'anio' => 'required|integer'
        ]);

        $jefe = $request->user();

        // Borra todo lo que coincida con ese mes, año y especialidad
        \App\Models\Guardia::where('especialidad_id', $jefe->especialidad_id)
            ->whereMonth('fecha', $request->mes)
            ->whereYear('fecha', $request->anio)
            ->delete();

        return back()->with('success', 'Calendario del mes limpiado correctamente.');
    }

    // 1. ELIMINAR UNA SOLA GUARDIA (Quirúrgico)
    public function destroy(Request $request, Guardia $guardia)
    {
        // Blindaje B2B: Nadie borra guardias de otro hospital
        if ($guardia->especialidad_id !== $request->user()->especialidad_id) {
            abort(403, 'Acceso denegado.');
        }

        $guardia->delete();
        
        return back()->with('success', 'Turno liberado correctamente.');
    }

    // 2. ELIMINAR EL MES COMPLETO (Nuclear)
    public function vaciarMes(Request $request)
    {
        $request->validate(['mes' => 'required|integer', 'anio' => 'required|integer']);

        \App\Models\Guardia::where('especialidad_id', $request->user()->especialidad_id)
            ->whereMonth('fecha', $request->mes)
            ->whereYear('fecha', $request->anio)
            ->delete();

        return back()->with('success', 'Calendario del mes reseteado por completo.');
    }

    // 3. INTERCAMBIO DE TURNOS (La Permuta)
    public function permutar(Request $request)
    {
        $request->validate([
            'origen_id' => 'required|exists:guardias,id',
            'destino_id' => 'required|exists:guardias,id',
        ]);

        $guardia1 = \App\Models\Guardia::find($request->origen_id);
        $guardia2 = \App\Models\Guardia::find($request->destino_id);

        // Blindaje B2B
        $hospitalId = $request->user()->especialidad_id;
        if ($guardia1->especialidad_id !== $hospitalId || $guardia2->especialidad_id !== $hospitalId) {
            abort(403);
        }

        // Intercambio seguro de los médicos
        $tempUserId = $guardia1->user_id;
        $guardia1->user_id = $guardia2->user_id;
        $guardia2->user_id = $tempUserId;

        // Al haber intervención humana, blindamos ambas guardias contra la IA
        $guardia1->is_manual = true;
        $guardia2->is_manual = true;

        $guardia1->save();
        $guardia2->save();

        return back()->with('success', 'Permuta realizada: Turnos intercambiados con éxito.');
    }

    public function exportarExcel(Request $request)
    {
        $mes = $request->query('mes', now()->month);
        $anio = $request->query('anio', now()->year);
        $especialidadId = $request->user()->especialidad_id;

        $nombreArchivo = "Cuadrante_Guardias_{$mes}_{$anio}.xlsx";

        return Excel::download(new GuardiasExport($especialidadId, $mes, $anio), $nombreArchivo);
    }

    public function exportarPdf(Request $request)
    {
        $mes = $request->query('mes', now()->month);
        $anio = $request->query('anio', now()->year);
        $usuario = $request->user();

        // Recuperamos los datos exactamente igual que en la vista web
        $guardias = \App\Models\Guardia::where('especialidad_id', $usuario->especialidad_id)
            ->with('facultativo')
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->orderBy('fecha')
            ->get();

        // DomPDF usa vistas Blade de Laravel (HTML) para generar el PDF
        $pdf = Pdf::loadView('reportes.guardias_pdf', [
            'guardias' => $guardias,
            'mes' => $mes,
            'anio' => $anio,
            'hospital' => $usuario->especialidad->hospital->nombre ?? 'Hospital',
            'especialidad' => $usuario->especialidad->nombre ?? 'Unidad'
        ]);

        return $pdf->download("Cuadrante_Guardias_{$mes}_{$anio}.pdf");
    }
}