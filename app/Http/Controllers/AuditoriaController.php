<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditoriaController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $usuario = $request->user();

        // Bloqueo de seguridad: Solo Jefes y SuperAdmins pueden ver la auditoría
        if (!$usuario->hasRole('Jefe de Servicio') && !$usuario->hasRole('SuperAdmin')) {
            abort(403, 'Acceso denegado. Se requiere rango de Jefatura.');
        }

        $filtros = [
            'busqueda' => trim((string) $request->query('busqueda', '')),
            'evento' => (string) $request->query('evento', 'todos'),
            'fecha_desde' => (string) $request->query('fecha_desde', ''),
            'fecha_hasta' => (string) $request->query('fecha_hasta', ''),
        ];

        $logsQuery = $this->construirQueryLogs($usuario, $filtros);

        $logs = $logsQuery
            ->latest()
            ->paginate(50)
            ->withQueryString()
            ->through(function (Activity $actividad): array {
                $modelo = $actividad->subject_type
                    ? class_basename($actividad->subject_type)
                    : ($actividad->log_name === 'cuadrante'
                        ? 'Cuadrante (Lote)'
                        : ($actividad->log_name === 'calendario' ? 'Calendario (Sync)' : 'Sistema'));

                return [
                    'id' => $actividad->id,
                    'evento' => $actividad->event,
                    'descripcion' => $actividad->description,
                    'causante' => $actividad->causer instanceof User ? $actividad->causer->name : 'Sistema/Desconocido',
                    'modelo' => $modelo,
                    'propiedades' => $actividad->properties,
                    'fecha' => $actividad->created_at?->format('d/m/Y H:i') ?? '',
                    'hace_tiempo' => $actividad->created_at?->diffForHumans() ?? '',
                ];
            });

        $esSuperAdmin = $usuario->hasRole('SuperAdmin');
        $especialidadId = (int) $usuario->especialidad_id;

        // F6-07: Panel de observabilidad de colas y sincronizaciones externas (aislado por tenant)
        $syncExitososQuery = Activity::where('log_name', 'calendario')->where('event', 'gcal_synced');
        $this->aplicarFiltroTenant($syncExitososQuery, $esSuperAdmin, $especialidadId);

        $syncFallidosQuery = Activity::where('log_name', 'calendario')->whereIn('event', ['gcal_sync_failed', 'gcal_sync_warning']);
        $this->aplicarFiltroTenant($syncFallidosQuery, $esSuperAdmin, $especialidadId);

        $usuariosSyncActivos = User::when(!$esSuperAdmin, fn($q) => $q->where('especialidad_id', $especialidadId))
            ->where('gcal_sync_enabled', true)
            ->count();

        $observabilidad = [
            'syncs_exitosos' => $syncExitososQuery->count(),
            'syncs_fallidos' => $syncFallidosQuery->count(),
            'usuarios_sync_activos' => $usuariosSyncActivos,
            'jobs_en_cola' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
            'jobs_fallidos' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
        ];

        return Inertia::render('Auditoria/Index', [
            'logs' => $logs,
            'observabilidad' => $observabilidad,
            'filtros' => $filtros,
        ]);
    }

    public function exportarCsv(Request $request): StreamedResponse
    {
        $usuario = $request->user();
        if (!$usuario->hasRole('Jefe de Servicio') && !$usuario->hasRole('SuperAdmin')) {
            abort(403, 'Acceso denegado. Se requiere rango de Jefatura.');
        }

        $filtros = [
            'busqueda' => trim((string) $request->query('busqueda', '')),
            'evento' => (string) $request->query('evento', 'todos'),
            'fecha_desde' => (string) $request->query('fecha_desde', ''),
            'fecha_hasta' => (string) $request->query('fecha_hasta', ''),
        ];

        $logs = $this->construirQueryLogs($usuario, $filtros)->latest()->limit(5000)->get();

        return response()->streamDownload(function () use ($logs): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Fecha', 'Evento', 'Modelo', 'Causante', 'Descripción'], ';');

            foreach ($logs as $actividad) {
                $modelo = $actividad->subject_type
                    ? class_basename($actividad->subject_type)
                    : ($actividad->log_name === 'cuadrante'
                        ? 'Cuadrante (Lote)'
                        : ($actividad->log_name === 'calendario' ? 'Calendario (Sync)' : 'Sistema'));

                fputcsv($handle, [
                    $actividad->id,
                    $actividad->created_at?->format('Y-m-d H:i:s') ?? '',
                    $actividad->event ?? '',
                    $modelo,
                    $actividad->causer instanceof User ? $actividad->causer->name : 'Sistema/Desconocido',
                    $actividad->description,
                ], ';');
            }

            fclose($handle);
        }, 'auditoria_clinicore_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param array{busqueda: string, evento: string, fecha_desde: string, fecha_hasta: string} $filtros
     * @return Builder<Activity>
     */
    private function construirQueryLogs(User $usuario, array $filtros): Builder
    {
        $esSuperAdmin = $usuario->hasRole('SuperAdmin');
        $especialidadId = (int) $usuario->especialidad_id;

        $query = Activity::with(['causer']);
        $this->aplicarFiltroTenant($query, $esSuperAdmin, $especialidadId);

        if ($filtros['evento'] === 'lotes') {
            $query->whereIn('event', ['batch_generated', 'batch_cleared']);
        } elseif ($filtros['evento'] === 'sync') {
            $query->whereIn('event', ['gcal_synced', 'gcal_sync_failed', 'gcal_sync_warning']);
        } elseif ($filtros['evento'] !== '' && $filtros['evento'] !== 'todos') {
            $query->where('event', $filtros['evento']);
        }

        if ($filtros['fecha_desde'] !== '') {
            $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
        }
        if ($filtros['fecha_hasta'] !== '') {
            $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
        }

        if ($filtros['busqueda'] !== '') {
            $termino = '%' . $filtros['busqueda'] . '%';
            $query->where(function (Builder $q) use ($termino): void {
                $q->where('description', 'like', $termino)
                    ->orWhere('subject_type', 'like', $termino)
                    ->orWhereHasMorph('causer', [User::class], fn(Builder $sub) => $sub->where('name', 'like', $termino));
            });
        }

        return $query;
    }

    /**
     * @param Builder<Activity> $query
     */
    private function aplicarFiltroTenant(Builder $query, bool $esSuperAdmin, int $especialidadId): void
    {
        if ($esSuperAdmin) {
            return;
        }

        $query->where(function (Builder $q) use ($especialidadId): void {
            $q->whereHasMorph('causer', [User::class], function (Builder $sub) use ($especialidadId): void {
                $sub->where('especialidad_id', $especialidadId);
            })->orWhere(function (Builder $sub) use ($especialidadId): void {
                $sub->whereNull('causer_id')
                    ->where('properties->especialidad_id', $especialidadId);
            });
        });
    }
}