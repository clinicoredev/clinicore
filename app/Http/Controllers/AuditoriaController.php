<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();

        // Bloqueo de seguridad: Solo Jefes y SuperAdmins pueden ver la auditoría
        if (!$usuario->hasRole('Jefe de Servicio') && !$usuario->hasRole('SuperAdmin')) {
            abort(403, 'Acceso denegado. Se requiere rango de Jefatura.');
        }

        $esSuperAdmin = $usuario->hasRole('SuperAdmin');
        $especialidadId = (int) $usuario->especialidad_id;

        $aplicarFiltroTenant = function ($query) use ($esSuperAdmin, $especialidadId) {
            if (!$esSuperAdmin) {
                $query->where(function ($q) use ($especialidadId) {
                    $q->whereHasMorph('causer', [User::class], function ($sub) use ($especialidadId) {
                        $sub->where('especialidad_id', $especialidadId);
                    })->orWhere(function ($sub) use ($especialidadId) {
                        $sub->whereNull('causer_id')
                            ->where('properties->especialidad_id', $especialidadId);
                    });
                });
            }
        };

        // Recuperamos la actividad filtrando estrictamente por la especialidad del usuario actual
        $logsQuery = Activity::with(['causer']);
        $aplicarFiltroTenant($logsQuery);

        $logs = $logsQuery
            ->latest()
            ->paginate(50)
            ->through(function ($actividad) {
                $modelo = $actividad->subject_type
                    ? class_basename($actividad->subject_type)
                    : ($actividad->log_name === 'cuadrante'
                        ? 'Cuadrante (Lote)'
                        : ($actividad->log_name === 'calendario' ? 'Calendario (Sync)' : 'Sistema'));

                return [
                    'id' => $actividad->id,
                    'evento' => $actividad->event, // created, updated, deleted, batch_generated, batch_cleared, gcal_synced, gcal_sync_failed
                    'descripcion' => $actividad->description,
                    'causante' => $actividad->causer ? $actividad->causer->name : 'Sistema/Desconocido',
                    'modelo' => $modelo,
                    'propiedades' => $actividad->properties, // Aquí están los datos viejos y nuevos
                    'fecha' => $actividad->created_at->format('d/m/Y H:i'),
                    'hace_tiempo' => $actividad->created_at->diffForHumans(),
                ];
            });

        // F6-07: Panel de observabilidad de colas y sincronizaciones externas (aislado por tenant)
        $syncExitososQuery = Activity::where('log_name', 'calendario')->where('event', 'gcal_synced');
        $aplicarFiltroTenant($syncExitososQuery);

        $syncFallidosQuery = Activity::where('log_name', 'calendario')->whereIn('event', ['gcal_sync_failed', 'gcal_sync_warning']);
        $aplicarFiltroTenant($syncFallidosQuery);

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
        ]);
    }
}