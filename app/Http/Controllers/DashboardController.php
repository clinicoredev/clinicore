<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\Guardia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
            $queryPendientes->where('user_id', $user->id); // El facultativo solo cuenta sus propias peticiones pendientes
        }
        $totalPendientes = $queryPendientes->count();

        // La tabla de la derecha del Dashboard:
        $colaPeticiones = Ausencia::where('especialidad_id', $especialidadId);
        if ($esJefe) {
            $colaPeticiones->where('estado', 'pendiente'); // Al Jefe le mostramos lo que tiene que firmar
        } else {
            $colaPeticiones->where('user_id', $user->id); // Al facultativo le mostramos un histórico reciente de lo suyo
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
        // 5. CÁLCULO DE FATIGA ACUMULADA (YTD) PARA LA GRÁFICA
        // ==========================================
        $guardiasAnio = Guardia::where('especialidad_id', $especialidadId)
            ->whereYear('fecha', $hoy->year)
            ->get();

        $mesesNombres = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        
        $puntosMiosPorMes = array_fill(0, 12, 0);
        $puntosTotalesPorMes = array_fill(0, 12, 0);

        // Agrupamos las guardias por mes y calculamos el "esfuerzo"
        foreach ($guardiasAnio as $g) {
            $mesIndice = Carbon::parse($g->fecha)->month - 1; // 0 = Enero, 11 = Diciembre
            $puntos = $g->tipo === 'festivo_24h' ? 2 : 1;
            
            $puntosTotalesPorMes[$mesIndice] += $puntos;
            
            if ($g->user_id === $user->id) {
                $puntosMiosPorMes[$mesIndice] += $puntos;
            }
        }

        // Convertimos a valores acumulados para que la línea siempre suba (Fatiga YTD)
        $acumuladoMio = [];
        $acumuladoMedia = [];
        $sumaMia = 0;
        $sumaMedia = 0;

        foreach ($mesesNombres as $i => $mes) {
            // Solo calculamos hasta el mes actual + 1 para que la gráfica no caiga a cero en el futuro
            if ($i > $hoy->month) {
                $acumuladoMio[] = null;
                $acumuladoMedia[] = null;
            } else {
                $sumaMia += $puntosMiosPorMes[$i];
                // La media es el total del departamento entre el número de médicos
                $sumaMedia += ($puntosTotalesPorMes[$i] / max(1, $totalMedicos)); 
                
                $acumuladoMio[] = $sumaMia;
                $acumuladoMedia[] = round($sumaMedia, 1);
            }
        }

        $graficaData = [
            'categorias' => $mesesNombres,
            'mis_puntos' => $acumuladoMio,
            'media_puntos' => $acumuladoMedia
        ];

        return Inertia::render('Dashboard', [
            'kpis' => [
                'total_medicos' => $totalMedicos,
                'limite_plan' => $limitePlan,
                'guardias_mes_servicio' => $guardiasMesServicio,
                'mis_guardias_mes' => $misGuardiasMes,
                'ausencias_pendientes' => $totalPendientes,
            ],
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
            'grafica_fatiga' => $graficaData
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