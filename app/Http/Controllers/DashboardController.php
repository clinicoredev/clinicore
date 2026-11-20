<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\Especialidad;
use App\Models\Guardia;
use App\Models\Hospital;
use App\Models\User;
use App\Services\GuardiaGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GuardiaGeneratorService $generatorService
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        if ($user->hasRole('SuperAdmin')) {
            return $this->generarDashboardSaaS();
        }

        // 1. AUTO-GENERAR TOKEN DE CALENDARIO SI NO EXISTE
        if (empty($user->calendar_token)) {
            $user->calendar_token = Str::random(40);
            $user->save();
        }

        $especialidadId = (int) $user->especialidad_id;
        $esJefe = $user->hasRole('Jefe de Servicio');
        $esTutor = $user->hasRole('Tutor de Residentes');
        $puedeRevisarAusencias = $esJefe || $esTutor;
        $puedeGestionarResidentes = $user->hasAnyRole(['Jefe de Servicio', 'Tutor de Residentes', 'Residente Mayor']);
        $hoy = Carbon::today();

        // 2. DATOS GLOBALES Y PERSONALES
        $usuariosServicio = User::where('especialidad_id', $especialidadId)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'SuperAdmin'))
            ->with('roles')
            ->get();

        $totalMedicos = $usuariosServicio->count();
        $limitePlan = $user->especialidad->limite_usuarios ?? 15;
        $guardiasMesServicio = Guardia::where('especialidad_id', $especialidadId)
            ->whereMonth('fecha', $hoy->month)
            ->whereYear('fecha', $hoy->year)
            ->count();
        $misGuardiasMes = Guardia::where('user_id', $user->id)
            ->whereMonth('fecha', $hoy->month)
            ->whereYear('fecha', $hoy->year)
            ->count();
        $miProximaGuardia = Guardia::where('user_id', $user->id)
            ->where('fecha', '>=', $hoy)
            ->orderBy('fecha', 'asc')
            ->first();

        // 3. BANDEJA DE PETICIONES (Jefe de Servicio revisa todas; Tutor de Residentes revisa las de Residentes)
        $queryPendientes = Ausencia::where('especialidad_id', $especialidadId)->where('estado', 'pendiente');
        if ($esJefe) {
            // Todas las pendientes del servicio
        } elseif ($esTutor) {
            $queryPendientes->whereHas('solicitante.roles', fn($q) => $q->whereIn('name', ['Residente', 'Residente Mayor']));
        } else {
            $queryPendientes->where('user_id', $user->id);
        }
        $totalPendientes = $queryPendientes->count();

        $colaQuery = Ausencia::where('especialidad_id', $especialidadId);
        if ($esJefe) {
            $colaQuery->where('estado', 'pendiente');
        } elseif ($esTutor) {
            $colaQuery->where('estado', 'pendiente')
                ->whereHas('solicitante.roles', fn($q) => $q->whereIn('name', ['Residente', 'Residente Mayor']));
        } else {
            $colaQuery->where('user_id', $user->id);
        }

        $colaPeticiones = $colaQuery
            ->with('solicitante:id,name')
            ->latest()
            ->take(4)
            ->get()
            ->map(function (Ausencia $a): array {
                $nombreSolicitante = $a->solicitante->name ?? 'Usuario eliminado';
                return [
                    'id' => $a->id,
                    'medico' => str_replace(['Dr. ', 'Dra. '], '', $nombreSolicitante),
                    'tipo' => ucfirst(str_replace('_', ' ', (string) $a->tipo)),
                    'fechas' => Carbon::parse($a->fecha_inicio)->format('d/m') . ' al ' . Carbon::parse($a->fecha_fin)->format('d/m'),
                    'estado' => $a->estado,
                ];
            });

        // 4. WIDGET HERO: Guardias de HOY separadas por colectivo (Adjuntos y Residentes)
        $mapaFestivosAnio = $this->generatorService->obtenerMapaFestivos($especialidadId, $hoy->year);

        $guardiasHoyItems = Guardia::where('especialidad_id', $especialidadId)
            ->whereDate('fecha', $hoy)
            ->with(['facultativo.roles'])
            ->get()
            ->map(function (Guardia $g) use ($mapaFestivosAnio): array {
                $facultativo = $g->facultativo;
                $esResidente = $facultativo
                    ? $facultativo->hasAnyRole(['Residente', 'Residente Mayor'])
                    : false;
                $info = $this->generatorService->clasificarTipologiaGuardia($g, $mapaFestivosAnio);

                return [
                    'id' => $g->id,
                    'user_id' => $g->user_id,
                    'medico' => str_replace(['Dr. ', 'Dra. '], '', $facultativo->name ?? 'Usuario eliminado'),
                    'colectivo' => $esResidente ? 'residentes' : 'adjuntos',
                    'colectivo_etiqueta' => $esResidente ? 'Residente' : 'Adjunto',
                    'tipo' => $info['etiqueta'],
                    'modalidad' => $info['modalidad'],
                    'etiqueta_modalidad' => $info['etiqueta_modalidad'],
                    'horas' => $info['horas'],
                    'horas_efectivas' => $info['horas_efectivas_computo'],
                    'es_especial' => $info['es_festivo_especial'],
                    'email' => $facultativo->email ?? '',
                    'observaciones' => $g->observaciones ?: 'Sin incidencias reportadas',
                ];
            })
            ->values();

        $guardiasHoyPorColectivo = [
            'adjuntos' => $guardiasHoyItems->where('colectivo', 'adjuntos')->values(),
            'residentes' => $guardiasHoyItems->where('colectivo', 'residentes')->values(),
            'todas' => $guardiasHoyItems,
        ];

        // 5. TRANSPARENCIA TOTAL: DESGLOSE ANUAL (YTD) DIFERENCIADO POR COLECTIVO
        $mapaColectivoUsuario = [];
        $mapaRolUsuario = [];
        foreach ($usuariosServicio as $u) {
            $esRes = $u->hasAnyRole(['Residente', 'Residente Mayor']);
            $mapaColectivoUsuario[$u->id] = $esRes ? 'residentes' : 'adjuntos';
            /** @var string|null $primerRol */
            $primerRol = $u->getRoleNames()->first();
            $mapaRolUsuario[$u->id] = $primerRol ?? ($esRes ? 'Residente' : 'Facultativo Adjunto');
        }

        $validUserIds = $usuariosServicio->pluck('id');

        // F4-03: Clasificación unificada usando GuardiaGeneratorService::clasificarTipologiaGuardia()
        $guardiasAnioServicio = Guardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $validUserIds)
            ->whereYear('fecha', $hoy->year)
            ->get()
            ->groupBy('user_id');

        $inicioTrimestre = $hoy->copy()->firstOfQuarter()->startOfDay();
        $finTrimestre = $hoy->copy()->lastOfQuarter()->endOfDay();
        $semanasTrimestre = max(1, (int) ceil($inicioTrimestre->diffInDays($finTrimestre) / 7));

        $desgloseAnual = $usuariosServicio
            ->map(function (User $u) use ($guardiasAnioServicio, $mapaFestivosAnio, $mapaColectivoUsuario, $mapaRolUsuario, $inicioTrimestre, $finTrimestre, $semanasTrimestre): array {
                $uId = (int) $u->id;
                $misGuardiasAnio = $guardiasAnioServicio->get($uId, collect());

                $g17 = 0;
                $g24Finde = 0;
                $g24Festivo = 0;
                $g24Especial = 0;
                $gLocalizadas = 0;
                $horasTotales = 0;
                $horasEfectivasTotales = 0;
                $horasTrimestre = 0;
                $semanasIsoTrimestre = [];

                foreach ($misGuardiasAnio as $g) {
                    $info = $this->generatorService->clasificarTipologiaGuardia($g, $mapaFestivosAnio);
                    $horasTotales += $info['horas'];
                    $horasEfectivasTotales += $info['horas_efectivas_computo'];

                    if ($info['modalidad'] === 'localizada') {
                        $gLocalizadas++;
                    }

                    $fCarbon = Carbon::parse($g->fecha);
                    if ($fCarbon->between($inicioTrimestre, $finTrimestre)) {
                        $horasTrimestre += $info['horas_efectivas_computo'];
                        $semKey = $fCarbon->format('o-\WW');
                        $semanasIsoTrimestre[$semKey] = ($semanasIsoTrimestre[$semKey] ?? 0) + $info['horas_efectivas_computo'];
                    }

                    if (!$info['es_24h']) {
                        $g17++;
                    } elseif ($info['es_festivo_oficial']) {
                        $g24Festivo++;
                        if ($info['es_festivo_especial']) {
                            $g24Especial++;
                        }
                    } else {
                        $g24Finde++;
                    }
                }

                $promedioSemanalTrimestre = round($horasTrimestre / $semanasTrimestre, 1);
                $picoSemanalTrimestre = !empty($semanasIsoTrimestre) ? max($semanasIsoTrimestre) : 0;

                return [
                    'id' => $uId,
                    'nombre' => (string) $u->name,
                    'colectivo' => $mapaColectivoUsuario[$uId] ?? 'adjuntos',
                    'rol' => $mapaRolUsuario[$uId] ?? 'Facultativo',
                    'guardias_17h' => $g17,
                    'guardias_24h_finde' => $g24Finde,
                    'guardias_24h_festivo' => $g24Festivo,
                    'guardias_24h_especial' => $g24Especial,
                    'guardias_localizadas' => $gLocalizadas,
                    'total_guardias' => $misGuardiasAnio->count(),
                    'horas_totales' => $horasEfectivasTotales,
                    'horas_nominales' => $horasTotales,
                    'horas_trimestre' => $horasTrimestre,
                    'promedio_semanal_trimestre' => $promedioSemanalTrimestre,
                    'pico_semanal_trimestre' => $picoSemanalTrimestre,
                ];
            })
            ->sortByDesc('total_guardias')
            ->values();

        // F6-07: Métricas informativas de cumplimiento de 48h/semana por trimestre (Directiva 2003/88/CE)
        $miFilaTrimestre = $desgloseAnual->firstWhere('id', (int) $user->id);
        $cumplimientoTrimestral48h = [
            'trimestre_etiqueta' => 'T' . $hoy->quarter . ' ' . $hoy->year,
            'semanas_evaluadas' => $semanasTrimestre,
            'mi_horas_trimestre' => (int) ($miFilaTrimestre['horas_trimestre'] ?? 0),
            'mi_promedio_semanal' => (float) ($miFilaTrimestre['promedio_semanal_trimestre'] ?? 0.0),
            'mi_pico_semanal' => (int) ($miFilaTrimestre['pico_semanal_trimestre'] ?? 0),
            'promedio_servicio_semanal' => round((float) ($desgloseAnual->avg('promedio_semanal_trimestre') ?? 0.0), 1),
            'usuarios_con_pico_mayor_48h' => $desgloseAnual->filter(fn(array $row): bool => $row['pico_semanal_trimestre'] > 48)->count(),
            'usuarios_con_promedio_mayor_48h' => $desgloseAnual->filter(fn(array $row): bool => $row['promedio_semanal_trimestre'] > 48.0)->count(),
        ];

        $miProximaGuardiaDto = null;
        if ($miProximaGuardia) {
            $proximaFechaCarbon = Carbon::parse($miProximaGuardia->fecha);
            $proximaFechaCarbon->locale('es');
            $infoProxima = $this->generatorService->clasificarTipologiaGuardia($miProximaGuardia, $mapaFestivosAnio);
            $miProximaGuardiaDto = [
                'fecha_legible' => ucfirst($proximaFechaCarbon->dayName . ', ' . $proximaFechaCarbon->format('d') . ' de ' . $proximaFechaCarbon->monthName),
                'tipo' => $infoProxima['etiqueta'],
                'dias_faltan' => $hoy->diffInDays($miProximaGuardia->fecha),
            ];
        }

        return Inertia::render('Dashboard', [
            'kpis' => [
                'total_medicos' => $totalMedicos,
                'limite_plan' => $limitePlan,
                'guardias_mes_servicio' => $guardiasMesServicio,
                'mis_guardias_mes' => $misGuardiasMes,
                'ausencias_pendientes' => $totalPendientes,
            ],
            'desglose_anual' => $desgloseAnual,
            'guardias_hoy' => $guardiasHoyPorColectivo,
            'guardia_hoy' => $guardiasHoyItems->first(),
            'mi_proxima_guardia' => $miProximaGuardiaDto,
            'cola_firmas' => $colaPeticiones,
            'token_calendario' => $user->calendar_token,
            'gcal_sync' => [
                'enabled' => (bool) ($user->gcal_sync_enabled ?? false),
                'calendar_id' => $user->gcal_calendar_id ?? 'primary',
                'last_synced_at' => $user->gcal_last_synced_at ? Carbon::parse($user->gcal_last_synced_at)->format('d/m/Y H:i') : null,
                'oauth_connected' => !empty($user->gcal_access_token),
                'oauth_configured' => trim((string) config('services.google.client_id', '')) !== ''
                    && trim((string) config('services.google.client_secret', '')) !== '',
            ],
            'cumplimiento_trimestral_48h' => $cumplimientoTrimestral48h,
            'permisos' => [
                'es_jefe' => $esJefe,
                'es_tutor' => $esTutor,
                'puede_revisar_ausencias' => $puedeRevisarAusencias,
                'puede_gestionar_residentes' => $puedeGestionarResidentes,
            ],
        ]);
    }

    private function generarDashboardSaaS(): InertiaResponse
    {
        return Inertia::render('Admin/DashboardSaaS', [
            'kpis_globales' => [
                'hospitales_activos' => Hospital::count(),
                'tenants_desplegados' => Especialidad::count(),
                'licencias_totales' => User::whereDoesntHave('roles', fn($q) => $q->where('name', 'SuperAdmin'))->count(),
                'guardias_calculadas_historico' => Guardia::count(),
            ],
        ]);
    }
}
