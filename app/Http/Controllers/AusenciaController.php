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

        $query = Ausencia::where('especialidad_id', $usuario->especialidad_id)
            ->with(['solicitante:id,name', 'revisor:id,name']);

        if (!$esJefe) {
            $query->where('user_id', $usuario->id);
        }

        $ausencias = $query->latest()->get()->map(function ($a) {
            return [
                'id' => $a->id,
                'user_id' => $a->user_id, // Necesario para el formulario de edición
                'tipo' => ucfirst(str_replace('_', ' ', $a->tipo)),
                'tipo_raw' => $a->tipo, // Necesario para el selector <select>
                'solicitante' => $a->solicitante->name,
                'revisor' => $a->revisor?->name ?? 'Pendiente de firma',
                'fecha_inicio' => $a->fecha_inicio->format('Y-m-d'), // Crudo para el <input type="date">
                'fecha_fin' => $a->fecha_fin->format('Y-m-d'),       // Crudo para el <input type="date">
                'fechas' => $a->fecha_inicio->format('d/m/Y') . ' al ' . $a->fecha_fin->format('d/m/Y'),
                'dias_totales' => $a->fecha_inicio->diffInDays($a->fecha_fin) + 1,
                'motivo' => $a->motivo ?? 'Sin especificar',
                'estado' => $a->estado,
            ];
        });

        $medicos = User::where('especialidad_id', $usuario->especialidad_id)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'SuperAdmin'))
            ->get(['id', 'name']);

        return Inertia::render('Ausencias/Index', [
            'ausencias' => $ausencias,
            'medicos' => $medicos,
            'permisos' => ['es_jefe' => $esJefe]
        ]);
    }

    public function store(Request $request)
    {
        $usuario = $request->user();
        $esJefe = $usuario->hasRole('Jefe de Servicio');

        $validated = $request->validate([
            'user_id' => [$esJefe ? 'required' : 'nullable', 'exists:users,id'],
            'tipo' => ['required', Rule::in(['congreso', 'vacaciones', 'asuntos_propios', 'baja_medica'])],
            'fecha_inicio' => ['required', 'date', $esJefe ? '' : 'after_or_equal:today'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $targetUserId = $esJefe ? $validated['user_id'] : $usuario->id;
        
        $estadoFinal = 'pendiente';
        $aprobadoPor = null;

        if ($esJefe) {
            $estadoFinal = 'aprobada';
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

    // NUEVO: MODIFICAR SOLICITUD
    public function update(Request $request, Ausencia $ausencia)
    {
        $usuario = $request->user();
        $esJefe = $usuario->hasRole('Jefe de Servicio');

        // Seguridad B2B (Mismo hospital)
        if ($ausencia->especialidad_id !== $usuario->especialidad_id) abort(403);
        
        if (!$esJefe) {
            if ($ausencia->user_id !== $usuario->id) abort(403);
            if ($ausencia->estado !== 'pendiente') abort(403, 'No puedes editar una ausencia que ya ha sido tramitada.');
        }

        $validated = $request->validate([
            'tipo' => ['required', Rule::in(['congreso', 'vacaciones', 'asuntos_propios', 'baja_medica'])],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        // Si la edita el jefe, podemos forzar un estado si queremos, pero lo normal es solo actualizar datos.
        $ausencia->update($validated);
        return back();
    }

    // NUEVO: ELIMINAR SOLICITUD
    public function destroy(Request $request, Ausencia $ausencia)
    {
        $usuario = $request->user();
        $esJefe = $usuario->hasRole('Jefe de Servicio');

        if ($ausencia->especialidad_id !== $usuario->especialidad_id) abort(403);

        if (!$esJefe) {
            if ($ausencia->user_id !== $usuario->id) abort(403);
            if ($ausencia->estado !== 'pendiente') abort(403, 'No puedes cancelar una ausencia que ya ha sido tramitada.');
        }

        $ausencia->delete();
        return back();
    }

    public function resolver(Request $request, Ausencia $ausencia)
    {
        if (!$request->user()->hasRole('Jefe de Servicio')) abort(403);

        $validated = $request->validate([
            'estado' => ['required', Rule::in(['aprobada', 'denegada'])],
        ]);

        $ausencia->update([
            'estado' => $validated['estado'],
            'aprobado_por' => $request->user()->id,
        ]);

        return back();
    }
}