<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

        // Recuperamos la actividad filtrando por la especialidad del usuario actual
        $logs = Activity::with(['causer'])
            ->whereHasMorph('causer', [\App\Models\User::class], function ($query) use ($usuario) {
                if (!$usuario->hasRole('SuperAdmin')) {
                    $query->where('especialidad_id', $usuario->especialidad_id);
                }
            })
            ->latest()
            ->paginate(50)
            ->through(function ($actividad) {
                return [
                    'id' => $actividad->id,
                    'evento' => $actividad->event, // created, updated, deleted
                    'descripcion' => $actividad->description,
                    'causante' => $actividad->causer ? $actividad->causer->name : 'Sistema/Desconocido',
                    'modelo' => class_basename($actividad->subject_type), // "Guardia" o "Ausencia"
                    'propiedades' => $actividad->properties, // Aquí están los datos viejos y nuevos
                    'fecha' => $actividad->created_at->format('d/m/Y H:i'),
                    'hace_tiempo' => $actividad->created_at->diffForHumans()
                ];
            });

        return Inertia::render('Auditoria/Index', [
            'logs' => $logs
        ]);
    }
}