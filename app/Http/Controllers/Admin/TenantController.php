<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvitacionSaaS;
use App\Models\Hospital;
use App\Models\Especialidad;
use App\Models\User;
use App\Models\Guardia;
use App\Models\LimitacionGuardia;
use App\Models\Ausencia;
use App\Models\Festivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TenantController extends Controller
{
    // ==========================================
    // 1. LECTURA Y GESTIÓN DE TENANTS (HOSPITALES)
    // ==========================================
    public function index()
    {
        $hospitales = Hospital::with([
            'especialidades.users' => function ($query) {
                $query->whereDoesntHave('roles', function ($q) {
                    $q->where('name', 'SuperAdmin');
                })->with('roles');
            }
        ])->get()->map(function ($hospital) {
            
            $usuarios = $hospital->especialidades->flatMap(function ($especialidad) {
                return $especialidad->users;
            })->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'especialidad_id' => $user->especialidad_id,
                    'roles' => $user->roles->map(fn($r) => ['name' => $r->name])
                ];
            });

            return [
                'id' => $hospital->id,
                'nombre' => $hospital->nombre,
                'total_medicos' => $usuarios->count(),
                'servicios' => $hospital->especialidades->map(fn($e) => [
                    'id' => $e->id,
                    'nombre' => $e->nombre,
                    'codigo' => $e->codigo,
                    'limite' => $e->limite_usuarios
                ]),
                'usuarios' => $usuarios->values() 
            ];
        });

        return Inertia::render('Admin/Tenants', [
            'hospitales' => $hospitales
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'hospital_nombre' => 'required|string|max:255',
            'especialidad_nombre' => 'required|string|max:255',
            'jefe_nombre' => 'required|string|max:255',
            'jefe_email' => 'required|email|unique:users,email',
            'jefe_rol' => ['nullable', Rule::in(['Jefe de Servicio', 'Residente Mayor'])],
            'enviar_invitacion' => 'nullable|boolean',
            'limite_usuarios' => 'nullable|integer|min:1|max:500',
        ]);

        $rolInicial = (string) ($data['jefe_rol'] ?? 'Jefe de Servicio');
        $enviarInvitacion = $request->boolean('enviar_invitacion', true);

        [$jefe, $passwordTemporal] = DB::transaction(function () use ($data, $rolInicial): array {
            $hospital = Hospital::create(['nombre' => $data['hospital_nombre']]);

            $especialidad = Especialidad::create([
                'hospital_id' => $hospital->id,
                'nombre' => $data['especialidad_nombre'],
                'codigo' => strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $data['especialidad_nombre']), 0, 3)) . '-' . rand(100, 999),
                'limite_usuarios' => $data['limite_usuarios'] ?? 15,
            ]);

            $passwordTemporal = Str::random(12);

            $jefe = User::forceCreate([
                'name' => $data['jefe_nombre'],
                'email' => $data['jefe_email'],
                'password' => Hash::make($passwordTemporal),
                'especialidad_id' => $especialidad->id,
                'email_verified_at' => now(),
                'calendar_token' => Str::random(40),
            ]);

            $jefe->assignRole($rolInicial);

            return [$jefe, $passwordTemporal];
        });

        if ($enviarInvitacion) {
            try {
                Mail::to($jefe->email)->send(new InvitacionSaaS($jefe));

                return back()->with('flash_toast', [
                    'tipo' => 'success',
                    'titulo' => 'Tenant Desplegado',
                    'mensaje' => "Unidad operativa lista ({$rolInicial}). Se ha enviado el enlace de activación a {$data['jefe_email']}.",
                ]);
            } catch (\Throwable $e) {
                return back()->with('flash_toast', [
                    'tipo' => 'success',
                    'titulo' => 'Tenant Desplegado (sin envío de email)',
                    'mensaje' => "Unidad lista ({$rolInicial}), pero falló el envío de correo. Credenciales: {$data['jefe_email']} | Contraseña: {$passwordTemporal}",
                ]);
            }
        }

        return back()->with('flash_toast', [
            'tipo' => 'success',
            'titulo' => 'Tenant Desplegado',
            'mensaje' => "Unidad operativa lista ({$rolInicial}). Credenciales: {$data['jefe_email']} | Contraseña: {$passwordTemporal}",
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['nombre' => 'required|string|max:255']);
        $hospital = Hospital::findOrFail($id);
        $hospital->update(['nombre' => $request->nombre]);

        return back();
    }

    public function destroy($id)
    {
        $hospital = Hospital::findOrFail($id);

        DB::transaction(function () use ($hospital) {
            $especialidadesIds = $hospital->especialidades()->pluck('id');

            // Limpieza total en cascada de todas las tablas del Tenant
            Guardia::whereIn('especialidad_id', $especialidadesIds)->delete();
            LimitacionGuardia::whereIn('especialidad_id', $especialidadesIds)->delete();
            Ausencia::whereIn('especialidad_id', $especialidadesIds)->delete();
            Festivo::whereIn('especialidad_id', $especialidadesIds)->delete();
            User::whereIn('especialidad_id', $especialidadesIds)->delete();
            
            $hospital->especialidades()->delete();
            $hospital->delete();
        });

        return back()->with('success', 'Hospital y todos sus datos asociados eliminados correctamente.');
    }

    // ==========================================
    // 2. GESTIÓN DE USUARIOS (DENTRO DEL TENANT)
    // ==========================================
    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'especialidad_id' => 'required|exists:especialidades,id',
            'role' => 'required|string|exists:roles,name' 
        ]);

        $especialidad = Especialidad::findOrFail($data['especialidad_id']);
        
        if ($especialidad->users()->count() >= $especialidad->limite_usuarios) {
            return back()->withErrors(['licencia' => "Límite alcanzado: Este Tenant solo permite {$especialidad->limite_usuarios} usuarios. Amplíe el plan."]);
        }

        $user = User::forceCreate([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'especialidad_id' => $data['especialidad_id'],
            'email_verified_at' => now(),
            'calendar_token' => Str::random(40),
        ]);

        $user->assignRole($data['role']);

        return back()->with('flash_toast', [
            'tipo' => 'success',
            'titulo' => 'Licencia Asignada',
            'mensaje' => "Se ha creado a {$data['name']} con el rol {$data['role']}."
        ]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|string|exists:roles,name'
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email']
        ]);

        $user->syncRoles([$data['role']]);

        return back();
    }

    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->id === request()->user()->id) {
            abort(403, 'Protocolo de seguridad: No puedes eliminar tu propia cuenta.');
        }

        $user->delete();

        return back();
    }

    // ==========================================
    // 3. MODO DIOS (IMPERSONATION)
    // ==========================================
    public function impersonate($id)
    {
        $adminId = auth()->id(); 
        $userToImpersonate = User::findOrFail($id); 
        
        auth()->login($userToImpersonate);
        session()->put('impersonated_by', $adminId);
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return Inertia::location(route('dashboard')); 
    }

    public function leaveImpersonate()
    {
        if (session()->has('impersonated_by')) {
            $adminId = session()->pull('impersonated_by'); 
            
            auth()->loginUsingId($adminId);
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            
            return Inertia::location(route('admin.tenants.index'));
        }
        
        return Inertia::location('/');
    }
}