<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\Guardia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // EL BIFURCADOR SUPERADMIN
        if ($user->hasRole('SuperAdmin')) {
            return $this->generarDashboardSaaS();
        }

        $especialidadId = $user->especialidad_id;
        $esJefe = $user->hasRole('Jefe de Servicio'); 
        $hoy = Carbon::today();

        // ==========================================
        // 1. DATOS GLOBALES (Visión Jefatura)
        // ==========================================
        $totalMedicos = User::where('especialidad_id', $especialidadId)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'SuperAdmin'))
            ->count();
        $limitePlan = $user->especialidad->limite_usuarios ?? 15;

        $guardiasMesServicio = Guardia::where('especialidad_id', $especialidadId)
            ->whereMonth('fecha', $hoy->month)
            ->whereYear('fecha', $hoy->year)
            ->count();

        // ==========================================
        // 2. DATOS PERSONALES (Visión Facultativo)
        // ==========================================
        $misGuardiasMes = Guardia::where('user_id', $user->id)
            ->whereMonth('fecha', $hoy->month)
            ->whereYear('fecha', $hoy->year)
            ->count();

        $miProximaGuardia = Guardia::where('user_id', $user->id)
            ->where('fecha', '>=', $hoy)
            ->orderBy('fecha', 'asc')
            ->first();

        // ==========================================
        // 3. BANDEJA DE PETICIONES (Ausencias)
        // ==========================================
        $queryPendientes = Ausencia::where('especialidad_id', $especialidadId)->where('estado', 'pendiente');
        if (!$esJefe) {
            $queryPendientes->where('user_id', $user->id); 
        }
        $totalPendientes = $queryPendientes->count();

        $colaPeticiones = Ausencia::where('especialidad_id', $especialidadId);
        if ($esJefe) {
            $colaPeticiones->where('estado', 'pendiente'); 
        } else {
            $colaPeticiones->where('user_id', $user->id); 
        }

        $colaPeticiones = $colaPeticiones->with('solicitante:id,name')
            ->latest()
            ->take(4)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'medico' => str_replace(['Dr. ', 'Dra. '], '', $a->solicitante->name),
                'tipo' => ucfirst(str_replace('_', ' ', $a->tipo)),
                'fechas' => $a->fecha_inicio->format('d/m') . ' al ' . $a->fecha_fin->format('d/m'),
                'estado' => $a->estado,
            ]);

        // ==========================================
        // 4. WIDGET HERO: ¿Quién de guardia HOY?
        // ==========================================
        $guardiaHoy = Guardia::where('especialidad_id', $especialidadId)
            ->whereDate('fecha', $hoy)
            ->with('facultativo:id,name,email')
            ->first();

        // ==========================================
        // 5. CONSULTA SQL AVANZADA DE DESGLOSE ANUAL (YTD)
        // ==========================================
        // Primero sacamos los IDs de los médicos válidos del servicio (evitando superadmins)
        $validUserIds = User::where('especialidad_id', $especialidadId)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'SuperAdmin'))
            ->pluck('id');

        $queryDesglose = DB::table('users as u')
            ->leftJoin('guardias as g', function($join) use ($hoy) {
                $join->on('u.id', '=', 'g.user_id')
                     ->whereYear('g.fecha', $hoy->year); // Solo contamos guardias de este año
            })
            ->leftJoin('festivos as f', function($join) {
                $join->on('g.fecha', '=', 'f.fecha')
                     ->on('g.especialidad_id', '=', 'f.especialidad_id');
            })
            ->whereIn('u.id', $validUserIds);

        // Si no es jefe, solo queremos ver su fila
        if (!$esJefe) {
            $queryDesglose->where('u.id', $user->id);
        }

        $desgloseAnual = $queryDesglose->selectRaw("
                u.id,
                u.name as nombre,
                SUM(CASE WHEN g.tipo = 'diaria_17h' THEN 1 ELSE 0 END) AS guardias_17h,
                SUM(CASE WHEN g.tipo = 'festivo_24h' AND f.id IS NULL THEN 1 ELSE 0 END) AS guardias_24h_finde,
                SUM(CASE WHEN g.tipo = 'festivo_24h' AND f.id IS NOT NULL THEN 1 ELSE 0 END) AS guardias_24h_festivo,
                COUNT(g.id) AS total_guardias
            ")
            ->groupBy('u.id', 'u.name')
            ->orderBy('total_guardias', 'desc')
            ->get();

        return Inertia::render('Dashboard', [
            'kpis' => [
                'total_medicos' => $totalMedicos,
                'limite_plan' => $limitePlan,
                'guardias_mes_servicio' => $guardiasMesServicio,
                'mis_guardias_mes' => $misGuardiasMes,
                'ausencias_pendientes' => $totalPendientes,
            ],
            'desglose_anual' => $desgloseAnual, 
            'guardia_hoy' => $guardiaHoy ? [
                'medico' => str_replace(['Dr. ', 'Dra. '], '', $guardiaHoy->facultativo->name),
                'tipo' => $guardiaHoy->tipo === 'festivo_24h' ? '24h (Fin de semana)' : '17h (Diaria)',
                'email' => $guardiaHoy->facultativo->email,
                'observaciones' => $guardiaHoy->observaciones ?? 'Sin incidencias reportadas'
            ] : null,
            'mi_proxima_guardia' => $miProximaGuardia ? [
                'fecha_legible' => ucfirst(Carbon::parse($miProximaGuardia->fecha)->locale('es')->translatedFormat('l, d \d\e F')),
                'tipo' => $miProximaGuardia->tipo === 'festivo_24h' ? '24h (Finde)' : '17h (Ordinaria)',
                'dias_faltan' => $hoy->diffInDays($miProximaGuardia->fecha)
            ] : null,
            'cola_firmas' => $colaPeticiones,
            'token_calendario' => $user->calendar_token,
            'permisos' => ['es_jefe' => $esJefe],
        ]);
    }

    private function generarDashboardSaaS()
    {
        return Inertia::render('Admin/DashboardSaaS', [
            'kpis_globales' => [
                'hospitales_activos' => \App\Models\Hospital::count(),
                'tenants_desplegados' => \App\Models\Especialidad::count(),
                'licencias_totales' => \App\Models\User::whereDoesntHave('roles', fn($q) => $q->where('name', 'SuperAdmin'))->count(),
                'guardias_calculadas_historico' => \App\Models\Guardia::count(),
            ]
        ]);
    }
}