<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AusenciaController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();
        
        $esJefe = $usuario->hasRole('Jefe de Servicio') || $usuario->hasRole('SuperAdmin');
        $esTutor = $usuario->hasRole('Tutor de Residentes');
        $modoRevisor = $esJefe || $esTutor;

        $query = Ausencia::where('especialidad_id', $usuario->especialidad_id)
            ->with(['solicitante:id,name', 'revisor:id,name']);

        if ($esJefe) {
            // El Jefe ve todas las ausencias del servicio
        } elseif ($esTutor) {
            // El Tutor ve las suyas propias + las de los residentes
            $query->where(function ($q) use ($usuario) {
                $q->where('user_id', $usuario->id)
                  ->orWhereHas('solicitante.roles', function ($q2) {
                      $q2->whereIn('name', ['Residente', 'Residente Mayor']);
                  });
            });
        } else {
            // El resto del personal (Adjuntos normales, Residente Mayor, Residente) solo ve las suyas
            $query->where('user_id', $usuario->id);
        }

        $ausencias = $query->latest()->get()->map(function ($a) {
            return [
                'id' => $a->id,
                'user_id' => $a->user_id,
                'tipo' => ucfirst(str_replace('_', ' ', $a->tipo)),
                'tipo_raw' => $a->tipo,
                'solicitante' => $a->solicitante->name,
                'revisor' => $a->revisor?->name ?? 'Pendiente de firma',
                'fecha_inicio' => $a->fecha_inicio->format('Y-m-d'),
                'fecha_fin' => $a->fecha_fin->format('Y-m-d'),
                'fechas' => $a->fecha_inicio->format('d/m/Y') . ' al ' . $a->fecha_fin->format('d/m/Y'),
                'dias_totales' => $a->fecha_inicio->diffInDays($a->fecha_fin) + 1,
                'motivo' => $a->motivo ?? 'Sin especificar',
                'estado' => $a->estado,
            ];
        });

        // Poblamos el selector para crear ausencias en nombre de terceros
        $medicos = User::where('especialidad_id', $usuario->especialidad_id)
            ->when($esTutor && !$esJefe, function($q) {
                // El tutor solo puede seleccionar a Residentes o a sí mismo
                $q->whereHas('roles', fn($r) => $r->whereIn('name', ['Residente', 'Residente Mayor']));
            })
            ->when(!$modoRevisor, function($q) use ($usuario) {
                // Un usuario normal solo se ve a sí mismo
                $q->where('id', $usuario->id);
            })
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'SuperAdmin'))
            ->get(['id', 'name']);

        // Agregamos al tutor a la lista si no estaba
        if ($esTutor && !$esJefe && !$medicos->contains('id', $usuario->id)) {
            $medicos->push((object)['id' => $usuario->id, 'name' => $usuario->name]);
        }

        return Inertia::render('Ausencias/Index', [
            'ausencias' => $ausencias,
            'medicos' => $medicos,
            'permisos' => ['es_jefe' => $modoRevisor] // Reutilizamos flag para activar la UI de Revisor
        ]);
    }

    public function store(Request $request)
    {
        $usuario = $request->user();
        $esJefe = $usuario->hasRole('Jefe de Servicio');
        $esTutor = $usuario->hasRole('Tutor de Residentes');
        $modoRevisor = $esJefe || $esTutor;

        $validated = $request->validate([
            'user_id' => [$modoRevisor ? 'required' : 'nullable', 'exists:users,id'],
            'tipo' => ['required', Rule::in(['congreso', 'vacaciones', 'asuntos_propios', 'baja_medica', 'rotacion', 'rotacion_con_guardias'])],
            'fecha_inicio' => ['required', 'date', $modoRevisor ? '' : 'after_or_equal:today'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $targetUserId = $modoRevisor ? $validated['user_id'] : $usuario->id;
        
        // Validar que el Tutor no intente añadir una ausencia a otro Adjunto
        if ($esTutor && !$esJefe && $targetUserId != $usuario->id) {
            $targetUser = User::findOrFail($targetUserId);
            if (!$targetUser->hasAnyRole(['Residente', 'Residente Mayor'])) {
                abort(403, 'Solo puedes tramitar ausencias de residentes.');
            }
        }

        $estadoFinal = 'pendiente';
        $aprobadoPor = null;

        // Auto-aprobar si un Revisor lo tramita en nombre de su subordinado
        if ($modoRevisor && $targetUserId != $usuario->id) {
            $estadoFinal = 'aprobada';
            $aprobadoPor = $usuario->id;
        } elseif ($esJefe && $targetUserId == $usuario->id) {
            $estadoFinal = 'aprobada'; // El jefe se autoaprueba
            $aprobadoPor = $usuario->id;
        }

        Ausencia::create([
            'especialidad_id' => $usuario->especialidad_id,
            'user_id' => $targetUserId,
            'tipo' => $validated['tipo'],
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_fin' => $validated['fecha_fin'],
            'motivo' => $validated['motivo'],
            'estado' => $estadoFinal,
            'aprobado_por' => $aprobadoPor
        ]);

        return back();
    }

    public function update(Request $request, Ausencia $ausencia)
    {
        $usuario = $request->user();
        $esJefe = $usuario->hasRole('Jefe de Servicio');
        $esTutor = $usuario->hasRole('Tutor de Residentes');

        if ($ausencia->especialidad_id !== $usuario->especialidad_id) abort(403);
        
        // Validación de permisos de edición
        if (!$esJefe) {
            if ($esTutor && $ausencia->user_id !== $usuario->id) {
                if (!$ausencia->solicitante->hasAnyRole(['Residente', 'Residente Mayor'])) abort(403);
            } elseif ($ausencia->user_id !== $usuario->id) {
                abort(403);
            }
            
            if ($ausencia->user_id === $usuario->id && $ausencia->estado !== 'pendiente') {
                abort(403, 'No puedes editar una ausencia propia que ya ha sido tramitada.');
            }
        }

        $validated = $request->validate([
            'tipo' => ['required', Rule::in(['congreso', 'vacaciones', 'asuntos_propios', 'baja_medica', 'rotacion', 'rotacion_con_guardias'])],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $ausencia->update($validated);
        return back();
    }

    public function destroy(Request $request, Ausencia $ausencia)
    {
        $usuario = $request->user();
        $esJefe = $usuario->hasRole('Jefe de Servicio');
        $esTutor = $usuario->hasRole('Tutor de Residentes');

        if ($ausencia->especialidad_id !== $usuario->especialidad_id) abort(403);

        if (!$esJefe) {
            if ($esTutor && $ausencia->user_id !== $usuario->id) {
                if (!$ausencia->solicitante->hasAnyRole(['Residente', 'Residente Mayor'])) abort(403);
            } elseif ($ausencia->user_id !== $usuario->id) {
                abort(403);
            }
            
            if ($ausencia->user_id === $usuario->id && $ausencia->estado !== 'pendiente') {
                abort(403, 'No puedes cancelar una ausencia propia que ya ha sido tramitada.');
            }
        }

        $ausencia->delete();
        return back();
    }

    public function resolver(Request $request, Ausencia $ausencia)
    {
        $usuario = $request->user();
        $esJefe = $usuario->hasRole('Jefe de Servicio');
        $esTutor = $usuario->hasRole('Tutor de Residentes');

        if (!$esJefe && !$esTutor) abort(403);

        if ($esTutor && !$esJefe) {
            $isResidente = $ausencia->solicitante->hasAnyRole(['Residente', 'Residente Mayor']);
            if (!$isResidente) abort(403, 'Solo puedes autorizar ausencias de residentes.');
        }

        $validated = $request->validate([
            'estado' => ['required', Rule::in(['aprobada', 'denegada'])],
        ]);

        $ausencia->update([
            'estado' => $validated['estado'],
            'aprobado_por' => $usuario->id,
        ]);

        return back();
    }
}