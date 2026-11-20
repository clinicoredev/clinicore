<?php

use App\Mail\InvitacionSaaS;
use App\Models\Ausencia;
use App\Models\Especialidad;
use App\Models\Festivo;
use App\Models\Guardia;
use App\Models\Hospital;
use App\Models\SolicitudPermuta;
use App\Models\User;
use App\Services\GuardiaGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['SuperAdmin', 'Jefe de Servicio', 'Tutor de Residentes', 'Facultativo', 'Facultativo Adjunto', 'Residente Mayor', 'Residente'] as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }
});

test('TenantController::store deja email_verified_at null cuando envia invitacion y permite onboarding completo; destroy limpia solicitudes_permuta', function () {
    Mail::fake();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('SuperAdmin');

    $this->actingAs($superAdmin)
        ->post(route('admin.tenants.store'), [
            'hospital_nombre' => 'Hospital Nuevo',
            'especialidad_nombre' => 'Urgencias',
            'jefe_nombre' => 'Dr. Nuevo Jefe',
            'jefe_email' => 'nuevo.jefe@clinicore.test',
            'enviar_invitacion' => true,
        ])
        ->assertRedirect();

    Mail::assertSent(InvitacionSaaS::class);

    $jefe = User::where('email', 'nuevo.jefe@clinicore.test')->firstOrFail();
    expect($jefe->email_verified_at)->toBeNull();

    $signedUrl = URL::temporarySignedRoute('onboarding.password', now()->addHours(24), ['user' => $jefe->id]);

    $this->get($signedUrl)->assertOk();
    $this->post($signedUrl, [
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('dashboard'));

    expect($jefe->fresh()?->email_verified_at)->not->toBeNull();

    // Una vez verificado, show() también debe rechazar con 403
    $this->get($signedUrl)->assertForbidden();

    // Verificar que TenantController::destroy limpia solicitudes_permuta y storeUser bloquea SuperAdmin
    $esp = Especialidad::where('id', $jefe->especialidad_id)->firstOrFail();
    $hospital = Hospital::where('id', $esp->hospital_id)->firstOrFail();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Intruso Admin',
            'email' => 'intruso.admin@clinicore.test',
            'password' => 'Password123!',
            'especialidad_id' => $esp->id,
            'role' => 'SuperAdmin',
        ])
        ->assertSessionHasErrors('role');

    $g1 = Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $jefe->id,
        'fecha' => now()->addDays(5)->toDateString(),
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    SolicitudPermuta::create([
        'especialidad_id' => $esp->id,
        'tipo_solicitud' => 'cobertura_vacante',
        'guardia_origen_id' => $g1->id,
        'solicitante_id' => $jefe->id,
        'colectivo' => 'adjuntos',
        'estado' => 'abierta_bolsa',
    ]);

    $this->actingAs($superAdmin)
        ->delete(route('admin.tenants.destroy', ['id' => $hospital->id]))
        ->assertRedirect();

    expect(SolicitudPermuta::where('especialidad_id', $esp->id)->count())->toBe(0)
        ->and(Hospital::where('id', $hospital->id)->count())->toBe(0);
});

test('FestivoController::update y copiarAnio gestionan festivos especiales por tenant y AuditoriaController exporta CSV filtrado', function () {
    $hospital = Hospital::create(['nombre' => 'Hospital Test']);
    $esp = Especialidad::create(['hospital_id' => $hospital->id, 'nombre' => 'Cardio', 'codigo' => 'CAR']);

    $jefe = User::factory()->create(['especialidad_id' => $esp->id]);
    $jefe->assignRole('Jefe de Servicio');

    $festivo = Festivo::create([
        'especialidad_id' => $esp->id,
        'fecha' => '2026-12-25',
        'descripcion' => 'Navidad',
    ]);

    $this->actingAs($jefe)
        ->patch(route('festivos.update', ['festivo' => $festivo->id]), [
            'fecha' => '2026-12-25',
            'descripcion' => 'Natividad del Señor',
            'es_especial' => true,
        ])
        ->assertRedirect();

    expect($festivo->fresh()?->descripcion)->toBe('[ESPECIAL] Natividad del Señor');

    $this->actingAs($jefe)
        ->post(route('festivos.copiarAnio'), [
            'anio_origen' => 2026,
            'anio_destino' => 2027,
        ])
        ->assertRedirect();

    expect(Festivo::where('especialidad_id', $esp->id)->whereDate('fecha', '2027-12-25')->exists())->toBeTrue();

    activity('cuadrante')
        ->event('batch_generated')
        ->causedBy($jefe)
        ->withProperties(['especialidad_id' => $esp->id])
        ->log('Cuadrante de prueba generado');

    $this->actingAs($jefe)
        ->get(route('auditoria.index', ['evento' => 'lotes', 'busqueda' => 'prueba']))
        ->assertOk();

    $csvResp = $this->actingAs($jefe)
        ->get(route('auditoria.exportar', ['evento' => 'lotes']));

    $csvResp->assertOk();
    expect($csvResp->streamedContent())->toContain('Cuadrante de prueba generado');
});

test('Normalizacion YTD por dias activos (2o mes nuevo, vuelta de baja larga, 50% jornada), usar_memoria_anual=false y periodo_ytd en Dashboard', function () {
    $hospital = Hospital::create(['nombre' => 'Hospital Equidad']);
    $esp = Especialidad::create(['hospital_id' => $hospital->id, 'nombre' => 'MI', 'codigo' => 'MI']);

    $veterano = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Veterano', 'porcentaje_jornada' => 100]);
    $veterano->assignRole('Facultativo');

    $nuevoMes2 = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Nuevo', 'porcentaje_jornada' => 100]);
    $nuevoMes2->assignRole('Facultativo');

    $vueltaBaja = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dra. VueltaBaja', 'porcentaje_jornada' => 100]);
    $vueltaBaja->assignRole('Facultativo');

    $parcial = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dra. Parcial50', 'porcentaje_jornada' => 50]);
    $parcial->assignRole('Facultativo');

    // Veterano trabajó ene-abr (4 guardias); Nuevo empezó en abril (1 guardia en abril); VueltaBaja tuvo baja ene-mar y 1 guardia en abril
    foreach (['2026-01-10', '2026-02-14', '2026-03-14', '2026-04-11'] as $f) {
        Guardia::create(['especialidad_id' => $esp->id, 'user_id' => $veterano->id, 'fecha' => $f, 'tipo' => 'festivo_24h', 'estado' => 'programada']);
    }
    Guardia::create(['especialidad_id' => $esp->id, 'user_id' => $nuevoMes2->id, 'fecha' => '2026-04-18', 'tipo' => 'festivo_24h', 'estado' => 'programada']);
    Guardia::create(['especialidad_id' => $esp->id, 'user_id' => $vueltaBaja->id, 'fecha' => '2026-04-25', 'tipo' => 'festivo_24h', 'estado' => 'programada']);

    Ausencia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $vueltaBaja->id,
        'tipo' => 'baja_medica',
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-03-31',
        'estado' => 'aprobada',
    ]);

    Festivo::create(['especialidad_id' => $esp->id, 'fecha' => '2026-05-01', 'descripcion' => 'Fiesta del Trabajo']);

    $service = app(GuardiaGeneratorService::class);
    $simConMemoria = $service->simularCuadrante($esp->id, GuardiaGeneratorService::COLECTIVO_ADJUNTOS, [
        'mes' => 5,
        'anio' => 2026,
        'personas_por_dia' => 1,
        'usar_memoria_anual' => true,
        'prorratear_ausencias' => true,
    ]);

    expect($simConMemoria['ok'])->toBeTrue()
        ->and($simConMemoria['guardias_simuladas'])->toHaveCount(31);

    $simSinMemoria = $service->simularCuadrante($esp->id, GuardiaGeneratorService::COLECTIVO_ADJUNTOS, [
        'mes' => 5,
        'anio' => 2026,
        'personas_por_dia' => 1,
        'usar_memoria_anual' => false,
    ]);

    expect($simSinMemoria['ok'])->toBeTrue()
        ->and($simSinMemoria['guardias_simuladas'])->toHaveCount(31);

    // Verificar periodo_ytd en Dashboard
    $this->actingAs($veterano)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->has('periodo_ytd.anio')
            ->has('periodo_ytd.desde')
            ->has('periodo_ytd.hasta')
            ->has('periodo_ytd.etiqueta')
        );
});
