<?php

use App\Models\Ausencia;
use App\Models\Especialidad;
use App\Models\Guardia;
use App\Models\Hospital;
use App\Models\LimitacionGuardia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['SuperAdmin', 'Jefe de Servicio', 'Tutor de Residentes', 'Facultativo', 'Residente', 'Residente Mayor'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

function crearTenantConUsuarios(): array
{
    $hospital = Hospital::create(['nombre' => 'Hospital Test']);
    $esp1 = Especialidad::create(['hospital_id' => $hospital->id, 'nombre' => 'Cardiología', 'codigo' => 'CARD', 'limite_usuarios' => 20]);
    $esp2 = Especialidad::create(['hospital_id' => $hospital->id, 'nombre' => 'Neurología', 'codigo' => 'NEUR', 'limite_usuarios' => 20]);

    $jefe1 = User::factory()->create(['especialidad_id' => $esp1->id]);
    $jefe1->assignRole('Jefe de Servicio');

    $adjunto1 = User::factory()->create(['especialidad_id' => $esp1->id]);
    $adjunto1->assignRole('Facultativo');

    $residente1 = User::factory()->create(['especialidad_id' => $esp1->id]);
    $residente1->assignRole('Residente');

    $residenteMayor1 = User::factory()->create(['especialidad_id' => $esp1->id]);
    $residenteMayor1->assignRole('Residente Mayor');

    $adjunto2 = User::factory()->create(['especialidad_id' => $esp2->id]);
    $adjunto2->assignRole('Facultativo');

    $residente2 = User::factory()->create(['especialidad_id' => $esp2->id]);
    $residente2->assignRole('Residente');

    return compact('hospital', 'esp1', 'esp2', 'jefe1', 'adjunto1', 'residente1', 'residenteMayor1', 'adjunto2', 'residente2');
}

test('F1-01: Onboarding POST rechaza peticiones sin firma válida y acepta firma válida', function () {
    $data = crearTenantConUsuarios();
    $user = $data['adjunto1'];

    $this->post("/bienvenido/{$user->id}", [
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertForbidden();

    $signedUrl = URL::temporarySignedRoute('onboarding.password', now()->addHours(24), ['user' => $user->id]);

    $this->post($signedUrl, [
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user->fresh());
});

test('F1-02: PersonalController::store bloquea usuarios sin rol Jefe de Servicio', function () {
    Mail::fake();
    $data = crearTenantConUsuarios();

    $this->actingAs($data['residente1'])
        ->post('/personal', [
            'name' => 'Intruso',
            'email' => 'intruso@clinicore.test',
            'rol' => 'Jefe de Servicio',
        ])->assertForbidden();

    $this->actingAs($data['jefe1'])
        ->post('/personal', [
            'name' => 'Nuevo Adjunto',
            'email' => 'nuevo@clinicore.test',
            'rol' => 'Facultativo',
        ])->assertRedirect();

    expect(User::where('email', 'nuevo@clinicore.test')->exists())->toBeTrue();
});

test('F1-03: Aislamiento multi-tenant en resolver() y destroyLimitacion()', function () {
    $data = crearTenantConUsuarios();

    $ausenciaOtroTenant = Ausencia::create([
        'especialidad_id' => $data['esp2']->id,
        'user_id' => $data['adjunto2']->id,
        'tipo' => 'vacaciones',
        'fecha_inicio' => now()->addDays(5)->toDateString(),
        'fecha_fin' => now()->addDays(7)->toDateString(),
        'estado' => 'pendiente',
    ]);

    $this->actingAs($data['jefe1'])
        ->patch("/ausencias/{$ausenciaOtroTenant->id}/resolver", ['estado' => 'aprobada'])
        ->assertForbidden();

    $limitacionOtroTenant = LimitacionGuardia::create([
        'especialidad_id' => $data['esp2']->id,
        'user_id' => $data['residente2']->id,
        'tipo' => 'dia_semana',
        'valor' => '1',
    ]);

    $this->actingAs($data['jefe1'])
        ->delete("/residentes/guardias/limitaciones/{$limitacionOtroTenant->id}")
        ->assertForbidden();
});

test('F1-04: storeLimitacion y guardarGuardiaManual validan tenant y colectivo', function () {
    $data = crearTenantConUsuarios();

    // Adjuntos no pueden asignar a un Residente ni a otro tenant
    $this->actingAs($data['jefe1'])
        ->post('/guardias/limitaciones', [
            'user_id' => $data['residente1']->id,
            'tipo' => 'dia_semana',
            'valor' => '1',
        ])->assertForbidden();

    $this->actingAs($data['jefe1'])
        ->post('/guardias/manual', [
            'user_id' => $data['adjunto2']->id,
            'fecha' => now()->addDays(3)->toDateString(),
            'tipo' => 'diaria_17h',
        ])->assertForbidden();

    // Residentes no pueden asignar a un Adjunto ni a otro tenant
    $this->actingAs($data['jefe1'])
        ->post('/residentes/guardias/limitaciones', [
            'user_id' => $data['adjunto1']->id,
            'tipo' => 'dia_semana',
            'valor' => '2',
        ])->assertForbidden();

    $this->actingAs($data['jefe1'])
        ->post('/residentes/guardias/manual', [
            'user_id' => $data['residente2']->id,
            'fecha' => now()->addDays(4)->toDateString(),
            'tipo' => 'diaria_17h',
        ])->assertForbidden();
});

test('F1-05: exportarPdf genera PDF correctamente en Adjuntos y Residentes', function () {
    $data = crearTenantConUsuarios();
    $fecha = now()->startOfMonth()->addDays(2)->toDateString();

    Guardia::create([
        'especialidad_id' => $data['esp1']->id,
        'user_id' => $data['adjunto1']->id,
        'fecha' => $fecha,
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    Guardia::create([
        'especialidad_id' => $data['esp1']->id,
        'user_id' => $data['residente1']->id,
        'fecha' => $fecha,
        'tipo' => 'festivo_24h',
        'estado' => 'programada',
    ]);

    $respAdjuntos = $this->actingAs($data['jefe1'])
        ->get('/guardias/exportar/pdf?mes=' . now()->month . '&anio=' . now()->year);
    $respAdjuntos->assertOk();
    expect($respAdjuntos->headers->get('content-type'))->toContain('application/pdf');

    $respResidentes = $this->actingAs($data['jefe1'])
        ->get('/residentes/guardias/exportar/pdf?mes=' . now()->month . '&anio=' . now()->year);
    $respResidentes->assertOk();
    expect($respResidentes->headers->get('content-type'))->toContain('application/pdf');
});

test('F1-07: permutar valida colisión de fecha y salientes, y Dashboard/Ausencias soportan SoftDeletes', function () {
    $data = crearTenantConUsuarios();

    $g1 = Guardia::create([
        'especialidad_id' => $data['esp1']->id,
        'user_id' => $data['jefe1']->id,
        'fecha' => '2026-11-10',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);
    $g2 = Guardia::create([
        'especialidad_id' => $data['esp1']->id,
        'user_id' => $data['adjunto1']->id,
        'fecha' => '2026-11-15',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);
    // Guardia adyacente que viola saliente si jefe1 pasa al día 15
    Guardia::create([
        'especialidad_id' => $data['esp1']->id,
        'user_id' => $data['jefe1']->id,
        'fecha' => '2026-11-16',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    $this->actingAs($data['jefe1'])
        ->post('/guardias/permutar', [
            'origen_id' => $g1->id,
            'destino_id' => $g2->id,
        ])->assertSessionHasErrors('conflicto');

    // SoftDelete de usuario con guardia hoy y ausencia pendiente no debe romper Dashboard ni Ausencias
    Guardia::create([
        'especialidad_id' => $data['esp1']->id,
        'user_id' => $data['adjunto1']->id,
        'fecha' => now()->toDateString(),
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);
    Ausencia::create([
        'especialidad_id' => $data['esp1']->id,
        'user_id' => $data['adjunto1']->id,
        'tipo' => 'congreso',
        'fecha_inicio' => now()->addDays(1)->toDateString(),
        'fecha_fin' => now()->addDays(2)->toDateString(),
        'estado' => 'pendiente',
    ]);

    $data['adjunto1']->delete();

    $this->actingAs($data['jefe1'])->get('/dashboard')->assertOk();
    $this->actingAs($data['jefe1'])->get('/ausencias')->assertOk();
});
