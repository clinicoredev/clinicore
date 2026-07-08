<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Especialidad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TenantController extends Controller
{
    // ==========================================
    // 1. LECTURA Y GESTIÓN DE TENANTS (HOSPITALES)
    // ==========================================
    public function index()
    {
        // 1. CARGA ANSIOSA RESTRINGIDA A NIVEL DE SQL
        $hospitales = Hospital::with([
            'especialidades.users' => function ($query) {
                // Filtramos en la base de datos: Excluir a cualquiera que sea SuperAdmin
                $query->whereDoesntHave('roles', function ($q) {
                    $q->where('name', 'SuperAdmin');
                })->with('roles'); // Cargamos los roles de los usuarios que sí pasaron el filtro
            }
        ])->get()->map(function ($hospital) {
            
            // 2. APLANADO DE USUARIOS (Ya vienen limpios desde la BD)
            $usuarios = $hospital->especialidades->flatMap(function ($especialidad) {
                return $especialidad->users;
            })->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->map(fn($r) => ['name' => $r->name])
                ];
            });

            // 3. CONSTRUCCIÓN DE LA RESPUESTA PARA VUE
            return [
                'id' => $hospital->id,
                'nombre' => $hospital->nombre,
                'total_medicos' => $usuarios->count(),
                'servicios' => $hospital->especialidades->map(fn($e) => [
                    'id' => $e->id,
                    'nombre' => $e->nombre,
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
        ]);

        return DB::transaction(function () use ($data) {
            $hospital = Hospital::create(['nombre' => $data['hospital_nombre']]);

            $especialidad = Especialidad::create([
                'hospital_id' => $hospital->id,
                'nombre' => $data['especialidad_nombre'],
                'codigo' => strtoupper(substr($data['especialidad_nombre'], 0, 3)) . '-' . rand(100, 999),
                'limite_usuarios' => 12
            ]);

            $passwordTemporal = Str::random(12);
            
            $jefe = User::create([
                'name' => $data['jefe_nombre'],
                'email' => $data['jefe_email'],
                'password' => Hash::make($passwordTemporal),
                'especialidad_id' => $especialidad->id,
            ]);

            // IMPORTANTE: Asegúrate de que el rol es exactamente el mismo que usas en Vue y Base de Datos
            $jefe->assignRole('Jefe de Servicio'); 

            return back()->with('flash_toast', [
                'tipo' => 'success',
                'titulo' => 'Tenant Desplegado',
                'mensaje' => "Unidad operativa lista. Credenciales: {$data['jefe_email']} | Contraseña: {$passwordTemporal}"
            ]);
        });
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

            \App\Models\Guardia::whereIn('especialidad_id', $especialidadesIds)->delete();
            \App\Models\LimitacionGuardia::whereIn('especialidad_id', $especialidadesIds)->delete();
            \App\Models\User::whereIn('especialidad_id', $especialidadesIds)->delete();
            
            $hospital->especialidades()->delete();
            $hospital->delete();
        });

        return back();
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
            // Valida que el rol que viene de Vue exista
            'role' => 'required|string|exists:roles,name' 
        ]);

        $especialidad = Especialidad::findOrFail($data['especialidad_id']);
        
        // 🚨 SISTEMA DE BILLING: Comprobar límite de licencias
        if ($especialidad->users()->count() >= $especialidad->limite_usuarios) {
            return back()->withErrors(['licencia' => "Límite alcanzado: Este Tenant solo permite {$especialidad->limite_usuarios} usuarios. Contacte con facturación para ampliar el plan."]);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'especialidad_id' => $data['especialidad_id'],
        ]);

        $user->assignRole($data['role']);

        return back()->with('flash_toast', [
            'tipo' => 'success',
            'titulo' => 'Licencia Asignada',
            'mensaje' => "Se ha creado a {$data['name']} correctamente."
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
    // 3. MODO DIOS (IMPERSONATION) CORREGIDO
    // ==========================================
    public function impersonate($id)
    {
        $adminId = auth()->id(); 
        $userToImpersonate = User::findOrFail($id); 
        
        auth()->login($userToImpersonate);
        session()->put('impersonated_by', $adminId);
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // MAGIA INERTIA: Forzamos recarga completa del navegador para actualizar el Layout
        return Inertia::location(route('guardias.index')); 
    }

    public function leaveImpersonate()
    {
        if (session()->has('impersonated_by')) {
            $adminId = session()->pull('impersonated_by'); 
            
            auth()->loginUsingId($adminId);
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            
            // MAGIA INERTIA: Forzamos recarga completa al volver a SuperAdmin
            return Inertia::location(route('admin.tenants.index'));
        }
        
        return Inertia::location('/');
    }
}