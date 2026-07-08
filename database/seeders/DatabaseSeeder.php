<?php

namespace Database\Seeders;

use App\Models\Especialidad;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Limpiar caché de permisos de Spatie (Vital para que no falle)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Crear Permisos granulares
        Permission::create(['name' => 'gestionar plataforma']); // Solo SuperAdmin
        Permission::create(['name' => 'gestionar servicio']);   // Jefes de Servicio
        Permission::create(['name' => 'crear casos']);
        Permission::create(['name' => 'solicitar ausencias']);
        Permission::create(['name' => 'aprobar ausencias']);

        // 3. Crear Roles y asignarles sus permisos
        $roleSuper = Role::create(['name' => 'SuperAdmin']);
        $roleSuper->givePermissionTo(Permission::all());

        $roleJefe = Role::create(['name' => 'Jefe de Servicio']);
        $roleJefe->givePermissionTo(['gestionar servicio', 'crear casos', 'solicitar ausencias', 'aprobar ausencias']);

        $roleFacultativo = Role::create(['name' => 'Facultativo']);
        $roleFacultativo->givePermissionTo(['crear casos', 'solicitar ausencias']);

        // 4. Crear el "Tenant" de prueba (Servicio de Cardiología)
        $cardio = Especialidad::create([
            'nombre' => 'Cardiología',
            'codigo' => 'CARDIO-01',
            'limite_usuarios' => 15,
            'almacenamiento_max_gb' => 20,
            'activo' => true
        ]);

        // 5. CREAR A LOS ACTORES DE LA DEMO

        // Actores Tier 1: Tú (SuperAdmin global)
        $admin = User::create([
            'name' => 'Jose Calvillo (SuperAdmin)',
            'email' => 'admin@clinicore.test',
            'password' => Hash::make('password'),
            'especialidad_id' => null, // El dueño del software no está atado a un hospital
        ]);
        $admin->assignRole('SuperAdmin');

        // Actores Tier 2: Dr. Gregory House (Jefe de Servicio de Cardiología)
        $jefe = User::create([
            'name' => 'Dr. Gregory House',
            'email' => 'house@clinicore.test',
            'password' => Hash::make('password'),
            'especialidad_id' => $cardio->id,
        ]);
        $jefe->assignRole('Jefe de Servicio');

        // Actores Tier 3: Dra. Allison Cameron (Facultativa normal)
        $medico = User::create([
            'name' => 'Dra. Allison Cameron',
            'email' => 'cameron@clinicore.test',
            'password' => Hash::make('password'),
            'especialidad_id' => $cardio->id,
        ]);
        $medico->assignRole('Facultativo');
    }
}