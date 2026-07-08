<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PersonalController extends Controller
{
    public function index(Request $request)
    {
        $jefe = $request->user();
        $esJefe = $jefe->hasRole('Jefe de Servicio') || $jefe->hasRole('SuperAdmin');
        $especialidad = $jefe->especialidad;

        $miembros = User::where('especialidad_id', $jefe->especialidad_id)
            // EL FILTRO MÁGICO: Excluir al Dios del sistema
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'SuperAdmin');
            })
            ->with('roles')
            ->get()
            ->map(function ($medico) {
                return [
                    'id' => $medico->id,
                    'name' => $medico->name,
                    'email' => $medico->email,
                    'rol' => $medico->roles->first()?->name ?? 'Facultativo',
                    'creado_el' => $medico->created_at->format('d/m/Y'),
                ];
            });

        $totalOcupado = $miembros->count();
        $limitePlan = $especialidad->limite_usuarios;

        return Inertia::render('Personal/Index', [
            'miembros' => $miembros,
            'metricas_plan' => [
                'total' => $totalOcupado,
                'maximo' => $limitePlan,
                'porcentaje' => min(round(($totalOcupado / $limitePlan) * 100), 100),
                'puede_invitar' => $totalOcupado < $limitePlan
            ],
            'permisos' => ['es_jefe' => $esJefe]
        ]);
    }

    public function store(Request $request)
    {
        $jefe = $request->user();
        $especialidad = $jefe->especialidad;

        if (User::where('especialidad_id', $jefe->especialidad_id)->count() >= $especialidad->limite_usuarios) {
            return back()->withErrors(['limite' => 'Plan al límite de capacidad.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'rol' => ['required', Rule::in(['Jefe de Servicio', 'Facultativo'])],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $nuevoMedico = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'especialidad_id' => $jefe->especialidad_id,
            'calendar_token' => Str::random(40),
        ]);

        $nuevoMedico->assignRole($validated['rol']);
        return back();
    }

    // 1. MODIFICAR DATOS DE UN MÉDICO
    public function update(Request $request, User $user)
    {
        $jefe = $request->user();
        
        // Blindaje Multi-Tenant: No puedes editar a un médico de otro hospital
        if (!$jefe->hasRole('Jefe de Servicio') || $user->especialidad_id !== $jefe->especialidad_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Validamos que el email sea único, PERO ignorando el del propio usuario que editamos
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)], 
            'rol' => ['required', Rule::in(['Jefe de Servicio', 'Facultativo'])],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $user->syncRoles([$validated['rol']]); // Spatie actualiza el cargo

        return back();
    }

    // 2. DESPEDIR A UN MÉDICO
    public function destroy(Request $request, User $user)
    {
        $jefe = $request->user();

        if (!$jefe->hasRole('Jefe de Servicio') || $user->especialidad_id !== $jefe->especialidad_id) {
            abort(403);
        }

        // EL ESCUDO ANTI-SUICIDIO:
        if ($user->id === $jefe->id) {
            return back()->withErrors(['suicide' => 'Protocolo de Seguridad: No puedes auto-eliminar tu cuenta principal de Jefatura.']);
        }

        $user->delete();
        return back();
    }
}