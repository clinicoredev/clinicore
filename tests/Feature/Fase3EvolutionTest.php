<?php

use App\Jobs\SyncGoogleCalendarJob;
use App\Models\Ausencia;
use App\Models\Especialidad;
use App\Models\Guardia;
use App\Models\Hospital;
use App\Models\LimitacionGuardia;
use App\Models\SolicitudPermuta;
use App\Models\User;
use App\Services\GuardiaGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['SuperAdmin', 'Jefe de Servicio', 'Tutor de Residentes', 'Facultativo Adjunto', 'Residente Mayor', 'Residente'] as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }
});

test('F3-01: Simulador Dry-Run devuelve guardias_simuladas, equidad_simulada y alertas_relajacion sin persistir en BD', function () {
    Queue::fake();

    $hospital = Hospital::create(['nombre' => 'Hospital Clínico']);
    $especialidad = Especialidad::create(['nombre' => 'Medicina Interna', 'codigo' => 'MIN', 'hospital_id' => $hospital->id]);

    $jefe = User::factory()->create(['especialidad_id' => $especialidad->id, 'name' => 'Dr. Jefe']);
    $jefe->assignRole('Jefe de Servicio');

    for ($i = 1; $i <= 5; $i++) {
        $m = User::factory()->create(['especialidad_id' => $especialidad->id, 'name' => "Dr. Adjunto {$i}"]);
        $m->assignRole('Facultativo Adjunto');
    }

    // Guardia manual previa que debe respetarse y no borrarse
    Guardia::create([
        'especialidad_id' => $especialidad->id,
        'user_id' => $jefe->id,
        'fecha' => '2026-09-15',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);

    $this->actingAs($jefe);

    // Con 6 médicos y 30 días (1 persona/día), un tope de max_guardias_mes = 3 forzará relajación de topes
    $response = $this->postJson('/guardias/simular', [
        'mes' => 9,
        'anio' => 2026,
        'personas_por_dia' => 1,
        'usar_plantilla_completa' => true,
        'respetar_salientes' => true,
        'distancia_minima_dias' => 1,
        'proteger_vispera_vacaciones' => true,
        'max_guardias_mes' => 3,
        'max_findes_mes' => 0,
        'max_diarias_mes' => 0,
        'prorratear_ausencias' => true,
        'usar_memoria_anual' => true,
        'agrupacion' => 'ninguna',
    ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('resumen.mes', 9)
        ->assertJsonPath('resumen.anio', 2026)
        ->assertJsonPath('resumen.turnos_nuevos', 29)
        ->assertJsonPath('resumen.manuales_respetados', 1);

    $json = $response->json();
    expect($json['guardias_simuladas'])->toHaveCount(30)
        ->and($json['equidad_simulada'])->toHaveCount(6)
        ->and($json['alertas_relajacion'])->not->toBeEmpty();

    // Verificar que la base de datos sigue teniendo únicamente la guardia manual inicial (Dry-Run puro)
    expect(Guardia::withoutGlobalScopes()->where('especialidad_id', $especialidad->id)->count())->toBe(1);
});

test('F3-02: Generacion masiva y vaciado de mes registran eventos de lote en activity_log aislados por especialidad_id', function () {
    Queue::fake();

    $hospital = Hospital::create(['nombre' => 'Hospital General']);
    $esp1 = Especialidad::create(['nombre' => 'Cirugía', 'codigo' => 'CIR', 'hospital_id' => $hospital->id]);
    $esp2 = Especialidad::create(['nombre' => 'Neurología', 'codigo' => 'NEU', 'hospital_id' => $hospital->id]);

    $jefe1 = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => 'Dr. Jefe Cirugía']);
    $jefe1->assignRole('Jefe de Servicio');

    for ($i = 1; $i <= 5; $i++) {
        $m = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => "Dr. Cirujano {$i}"]);
        $m->assignRole('Facultativo Adjunto');
    }

    $jefe2 = User::factory()->create(['especialidad_id' => $esp2->id, 'name' => 'Dr. Jefe Neuro']);
    $jefe2->assignRole('Jefe de Servicio');

    $this->actingAs($jefe1);

    $this->post('/guardias/generar', [
        'mes' => 10,
        'anio' => 2026,
        'personas_por_dia' => 1,
        'usar_plantilla_completa' => true,
        'respetar_salientes' => true,
        'distancia_minima_dias' => 1,
        'proteger_vispera_vacaciones' => true,
        'agrupacion' => 'ninguna',
    ])->assertRedirect();

    $logGen = Activity::where('event', 'batch_generated')->latest('id')->first();
    expect($logGen)->not->toBeNull()
        ->and($logGen->causer_id)->toBe($jefe1->id)
        ->and($logGen->properties['especialidad_id'])->toBe($esp1->id)
        ->and($logGen->properties['attributes']['turnos_generados'])->toBe(31);

    $this->delete('/guardias/vaciar-mes?mes=10&anio=2026')->assertRedirect();

    $logClear = Activity::where('event', 'batch_cleared')->latest('id')->first();
    expect($logClear)->not->toBeNull()
        ->and($logClear->causer_id)->toBe($jefe1->id)
        ->and($logClear->properties['especialidad_id'])->toBe($esp1->id)
        ->and($logClear->properties['old']['turnos_eliminados'])->toBe(31);

    // El Jefe del Tenant 2 NO debe ver los logs de lote del Tenant 1 en /auditoria
    $this->actingAs($jefe2);
    $respAuditoria = $this->get('/auditoria');
    $respAuditoria->assertOk();
    $logsData = $respAuditoria->viewData('page')['props']['logs']['data'];
    $eventosTenant2 = collect($logsData)->whereIn('evento', ['batch_generated', 'batch_cleared']);
    expect($eventosTenant2)->toBeEmpty();
});

test('F3-03: Detector de conflictos en tiempo real detecta salientes, ausencias y bloquea acceso cross-tenant', function () {
    $hospital = Hospital::create(['nombre' => 'Hospital Universitario']);
    $esp1 = Especialidad::create(['nombre' => 'Anestesia', 'codigo' => 'ANE', 'hospital_id' => $hospital->id]);
    $esp2 = Especialidad::create(['nombre' => 'Urología', 'codigo' => 'URO', 'hospital_id' => $hospital->id]);

    $jefe = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => 'Dr. Jefe Anestesia']);
    $jefe->assignRole('Jefe de Servicio');

    $m1 = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => 'Dr. Uno']);
    $m1->assignRole('Facultativo Adjunto');

    $m2 = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => 'Dra. Dos']);
    $m2->assignRole('Facultativo Adjunto');

    $mOtroTenant = User::factory()->create(['especialidad_id' => $esp2->id, 'name' => 'Dr. Externo']);
    $mOtroTenant->assignRole('Facultativo Adjunto');

    // m1 tiene guardia el 2026-11-10 y el 2026-11-14; m2 tiene guardia el 2026-11-15 y vacaciones el 2026-11-10
    $g1 = Guardia::create([
        'especialidad_id' => $esp1->id,
        'user_id' => $m1->id,
        'fecha' => '2026-11-10',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    Guardia::create([
        'especialidad_id' => $esp1->id,
        'user_id' => $m1->id,
        'fecha' => '2026-11-14',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    $g2 = Guardia::create([
        'especialidad_id' => $esp1->id,
        'user_id' => $m2->id,
        'fecha' => '2026-11-15',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    Ausencia::create([
        'especialidad_id' => $esp1->id,
        'user_id' => $m2->id,
        'tipo' => 'vacaciones',
        'fecha_inicio' => '2026-11-09',
        'fecha_fin' => '2026-11-11',
        'estado' => 'aprobada',
    ]);

    LimitacionGuardia::create([
        'especialidad_id' => $esp1->id,
        'user_id' => $m1->id,
        'tipo' => 'fecha_concreta',
        'valor' => '2026-11-20',
        'motivo' => 'Consulta especial',
    ]);

    $this->actingAs($jefe);

    // 1. Verificar asignación manual con veto en 2026-11-20
    $respManual = $this->postJson('/guardias/verificar-conflicto', [
        'modo' => 'asignacion',
        'user_id' => $m1->id,
        'fecha' => '2026-11-20',
    ]);
    $respManual->assertOk()
        ->assertJsonPath('valido', false)
        ->assertJsonPath('forzable', true);
    expect($respManual->json('conflictos.0.tipo'))->toBe('limitacion_fecha');

    // 2. Verificar permuta entre g1 (2026-11-10) y g2 (2026-11-15):
    // m1 tendría saliente con su guardia del 2026-11-14, y m2 tiene vacaciones el 2026-11-10
    $respPermuta = $this->postJson('/guardias/verificar-conflicto', [
        'modo' => 'permuta',
        'origen_id' => $g1->id,
        'destino_id' => $g2->id,
    ]);
    $respPermuta->assertOk()
        ->assertJsonPath('valido', false)
        ->assertJsonPath('bloqueante', true);

    $tipos = collect($respPermuta->json('conflictos'))->pluck('tipo')->all();
    expect($tipos)->toContain('saliente_distancia_minima')
        ->and($tipos)->toContain('ausencia');

    // 3. Intento cross-tenant debe devolver 403
    $this->postJson('/guardias/verificar-conflicto', [
        'modo' => 'asignacion',
        'user_id' => $mOtroTenant->id,
        'fecha' => '2026-11-20',
    ])->assertForbidden();
});

test('F3-04 y F3-05: Flujo de solicitud de intercambio con aprobacion por Tutor/Jefe, aislamiento multi-tenant y SyncGoogleCalendarJob', function () {
    Queue::fake();

    $hospital = Hospital::create(['nombre' => 'Hospital Infantil']);
    $esp1 = Especialidad::create(['nombre' => 'Pediatría', 'codigo' => 'PED', 'hospital_id' => $hospital->id]);
    $esp2 = Especialidad::create(['nombre' => 'Dermatología', 'codigo' => 'DER', 'hospital_id' => $hospital->id]);

    $tutor = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => 'Dr. Tutor MIR']);
    $tutor->assignRole('Tutor de Residentes');

    $r1 = User::factory()->create([
        'especialidad_id' => $esp1->id,
        'name' => 'Residente Uno',
        'gcal_sync_enabled' => true,
        'gcal_calendar_id' => 'r1@gmail.com',
    ]);
    $r1->assignRole('Residente');

    $r2 = User::factory()->create([
        'especialidad_id' => $esp1->id,
        'name' => 'Residente Dos',
        'gcal_sync_enabled' => true,
    ]);
    $r2->assignRole('Residente');

    $jefeOtroTenant = User::factory()->create(['especialidad_id' => $esp2->id, 'name' => 'Dr. Jefe Derma']);
    $jefeOtroTenant->assignRole('Jefe de Servicio');

    $fecha1 = now()->addDays(10)->format('Y-m-d');
    $fecha2 = now()->addDays(18)->format('Y-m-d');

    $gRes1 = Guardia::create([
        'especialidad_id' => $esp1->id,
        'user_id' => $r1->id,
        'fecha' => $fecha1,
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    $gRes2 = Guardia::create([
        'especialidad_id' => $esp1->id,
        'user_id' => $r2->id,
        'fecha' => $fecha2,
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    // 1. r1 propone intercambio con r2
    $this->actingAs($r1);
    $this->post('/permutas', [
        'guardia_origen_id' => $gRes1->id,
        'guardia_destino_id' => $gRes2->id,
        'motivo' => 'Examen de máster',
    ])->assertRedirect();

    $solicitud = SolicitudPermuta::withoutGlobalScopes()->first();
    expect($solicitud)->not->toBeNull()
        ->and($solicitud->especialidad_id)->toBe($esp1->id)
        ->and($solicitud->estado)->toBe('pendiente_companero')
        ->and($solicitud->colectivo)->toBe(GuardiaGeneratorService::COLECTIVO_RESIDENTES);

    // 2. Jefe de otro tenant NO puede resolver la solicitud (403 por BelongsToEspecialidad)
    $this->actingAs($jefeOtroTenant);
    $this->patch("/permutas/{$solicitud->id}/resolver", ['estado' => 'aprobada'])
        ->assertForbidden();

    // 2b. El compañero destinatario (r2) acepta la solicitud (pasa a pendiente_aprobacion)
    $this->actingAs($r2);
    $this->patch("/permutas/{$solicitud->id}/responder-companero", ['accion' => 'aceptar'])
        ->assertRedirect();
    expect($solicitud->fresh()->estado)->toBe('pendiente_aprobacion');

    // 3. El Tutor de Residentes aprueba la solicitud de residentes y se intercambian las guardias
    $this->actingAs($tutor);
    $this->patch("/permutas/{$solicitud->id}/resolver", ['estado' => 'aprobada'])
        ->assertRedirect();

    expect($solicitud->fresh()->estado)->toBe('aprobada')
        ->and($solicitud->fresh()->revisado_por)->toBe($tutor->id)
        ->and($gRes1->fresh()->user_id)->toBe($r2->id)
        ->and($gRes2->fresh()->user_id)->toBe($r1->id);

    // 4. Verificar que se encoló SyncGoogleCalendarJob y que su ejecución actualiza gcal_last_synced_at
    Queue::assertPushed(SyncGoogleCalendarJob::class, function (SyncGoogleCalendarJob $job) use ($esp1, $r1, $r2) {
        return $job->especialidadId === $esp1->id
            && in_array($r1->id, $job->userIds, true)
            && in_array($r2->id, $job->userIds, true);
    });

    $job = new SyncGoogleCalendarJob($esp1->id, [$r1->id, $r2->id]);
    $job->handle(app(GuardiaGeneratorService::class));

    expect($r1->fresh()->gcal_last_synced_at)->not->toBeNull()
        ->and($r2->fresh()->gcal_last_synced_at)->not->toBeNull();
});
