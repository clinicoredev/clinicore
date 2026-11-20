<?php

use App\Jobs\SyncGoogleCalendarJob;
use App\Models\Ausencia;
use App\Models\Especialidad;
use App\Models\Guardia;
use App\Models\Hospital;
use App\Models\SolicitudPermuta;
use App\Models\User;
use App\Notifications\CuadrantePublicadoNotification;
use App\Notifications\EstadoAusenciaModificado;
use App\Notifications\PermutaPropuestaNotification;
use App\Notifications\PermutaResueltaNotification;
use App\Services\GuardiaGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['SuperAdmin', 'Jefe de Servicio', 'Tutor de Residentes', 'Facultativo', 'Facultativo Adjunto', 'Residente Mayor', 'Residente'] as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }
});

test('F5-01 y F5-04: Doble validacion en permutas (companero -> jefatura), bloqueo de fechas pasadas, duplicados y notificaciones', function () {
    Notification::fake();
    Queue::fake();

    $hospital = Hospital::create(['nombre' => 'Hospital Universitario']);
    $esp = Especialidad::create(['nombre' => 'Cirugía General', 'codigo' => 'CIR', 'hospital_id' => $hospital->id]);

    $jefe = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Jefe Cirugía']);
    $jefe->assignRole('Jefe de Servicio');

    $m1 = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Solicitante']);
    $m1->assignRole('Facultativo');

    $m2 = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dra. Destinataria']);
    $m2->assignRole('Facultativo');

    $gPasada = Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $m1->id,
        'fecha' => now()->subDays(3)->format('Y-m-d'),
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    $g1 = Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $m1->id,
        'fecha' => now()->addDays(10)->format('Y-m-d'),
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    $g2 = Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $m2->id,
        'fecha' => now()->addDays(17)->format('Y-m-d'),
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    $this->actingAs($m1);

    // 1. Rechazar permuta sobre guardia en fecha pasada
    $this->post('/permutas', [
        'guardia_origen_id' => $gPasada->id,
        'guardia_destino_id' => $g2->id,
    ])->assertSessionHasErrors('conflicto');

    // 2. Crear solicitud válida -> nace en 'pendiente_companero' y notifica a m2
    $this->post('/permutas', [
        'guardia_origen_id' => $g1->id,
        'guardia_destino_id' => $g2->id,
        'motivo' => 'Asunto familiar',
    ])->assertRedirect();

    $solicitud = SolicitudPermuta::first();
    expect($solicitud)->not->toBeNull()
        ->and($solicitud->estado)->toBe('pendiente_companero');

    Notification::assertSentTo($m2, PermutaPropuestaNotification::class);

    // 3. Bloquear solicitud duplicada pendiente sobre los mismos turnos
    $this->post('/permutas', [
        'guardia_origen_id' => $g1->id,
        'guardia_destino_id' => $g2->id,
    ])->assertSessionHasErrors('conflicto');

    // 4. Jefatura NO puede aprobar antes de que el compañero acepte
    $this->actingAs($jefe)
        ->patch("/permutas/{$solicitud->id}/resolver", ['estado' => 'aprobada'])
        ->assertSessionHasErrors('conflicto');

    // 5. La compañera destinataria (m2) acepta -> pasa a 'pendiente_aprobacion' y notifica al Jefe
    $this->actingAs($m2)
        ->patch("/permutas/{$solicitud->id}/responder-companero", ['accion' => 'aceptar'])
        ->assertRedirect();

    expect($solicitud->fresh()->estado)->toBe('pendiente_aprobacion')
        ->and($solicitud->fresh()->aceptado_destinatario_at)->not->toBeNull();

    Notification::assertSentTo($jefe, PermutaPropuestaNotification::class);

    // 6. El Jefe aprueba -> intercambia guardias y notifica a m1 y m2
    $this->actingAs($jefe)
        ->patch("/permutas/{$solicitud->id}/resolver", ['estado' => 'aprobada'])
        ->assertRedirect();

    expect($solicitud->fresh()->estado)->toBe('aprobada')
        ->and($g1->fresh()->user_id)->toBe($m2->id)
        ->and($g2->fresh()->user_id)->toBe($m1->id);

    Notification::assertSentTo([$m1, $m2], PermutaResueltaNotification::class);
});

test('F5-02, F5-03 y F5-04: Exencion de guardias, ponderacion por % jornada, modalidad localizada, alerta 48h y notificaciones', function () {
    Notification::fake();
    Queue::fake();

    $hospital = Hospital::create(['nombre' => 'Hospital Norte']);
    $esp = Especialidad::create(['nombre' => 'Neurología', 'codigo' => 'NEU', 'hospital_id' => $hospital->id]);

    $jefe = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Jefe']);
    $jefe->assignRole('Jefe de Servicio');

    $mExento = User::factory()->create([
        'especialidad_id' => $esp->id,
        'name' => 'Dr. Exento >55',
        'exento_guardias' => true,
        'motivo_exencion' => 'Mayor de 55 años',
    ]);
    $mExento->assignRole('Facultativo');

    $mReducido = User::factory()->create([
        'especialidad_id' => $esp->id,
        'name' => 'Dra. Reducción 50%',
        'porcentaje_jornada' => 50,
    ]);
    $mReducido->assignRole('Facultativo');

    for ($i = 1; $i <= 5; $i++) {
        $m = User::factory()->create([
            'especialidad_id' => $esp->id,
            'name' => "Dr. Completo {$i}",
            'porcentaje_jornada' => 100,
        ]);
        $m->assignRole('Facultativo');
    }

    $service = app(GuardiaGeneratorService::class);

    // 1. checkConflict detecta exención de guardias
    expect($service->checkConflict($mExento->id, '2026-11-12'))->toContain('exento de realizar guardias');

    // 2. Clasificación de guardia localizada (cuenta las horas igual que presencial pero distingue modalidad)
    $gLocalizada = new Guardia([
        'especialidad_id' => $esp->id,
        'user_id' => $mReducido->id,
        'fecha' => '2026-11-14', // Sábado 24h
        'tipo' => 'festivo_24h',
        'modalidad' => 'localizada',
    ]);
    $infoLoc = $service->clasificarTipologiaGuardia($gLocalizada, []);
    expect($infoLoc['horas'])->toBe(24)
        ->and($infoLoc['horas_efectivas_computo'])->toBe(24)
        ->and($infoLoc['modalidad'])->toBe('localizada')
        ->and($infoLoc['etiqueta'])->toContain('[Localizada]');

    // 3. Generación del cuadrante excluye a mExento, pondera a mReducido y envía CuadrantePublicadoNotification
    $error = $service->generarCuadrante($esp->id, GuardiaGeneratorService::COLECTIVO_ADJUNTOS, [
        'mes' => 11,
        'anio' => 2026,
        'personas_por_dia' => 1,
        'usar_plantilla_completa' => true,
        'respetar_salientes' => true,
        'distancia_minima_dias' => 1,
        'agrupacion' => 'ninguna',
    ]);
    expect($error)->toBeNull();

    $turnosExento = Guardia::withoutGlobalScopes()
        ->where('especialidad_id', $esp->id)
        ->where('user_id', $mExento->id)
        ->count();
    expect($turnosExento)->toBe(0);

    Notification::assertSentTo($mReducido, CuadrantePublicadoNotification::class);

    // 4. Resolver una ausencia envía EstadoAusenciaModificado
    $ausencia = Ausencia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $mReducido->id,
        'tipo' => 'congreso',
        'fecha_inicio' => '2026-12-01',
        'fecha_fin' => '2026-12-03',
        'estado' => 'pendiente',
    ]);

    $this->actingAs($jefe)
        ->patch("/ausencias/{$ausencia->id}/resolver", ['estado' => 'aprobada'])
        ->assertRedirect();

    Notification::assertSentTo($mReducido, EstadoAusenciaModificado::class);
});

test('F5-05: Integracion OAuth2 con Google Calendar API (callback y sincronizacion de eventos con Http::fake)', function () {
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'ya29.fake-access-token',
            'refresh_token' => '1//fake-refresh-token',
            'expires_in' => 3600,
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/*' => Http::response([
            'id' => 'clinicoreguardia1',
            'status' => 'confirmed',
        ], 200),
    ]);

    $hospital = Hospital::create(['nombre' => 'Hospital Clínico']);
    $esp = Especialidad::create(['nombre' => 'Radiología', 'codigo' => 'RAD', 'hospital_id' => $hospital->id]);

    $medico = User::factory()->create([
        'especialidad_id' => $esp->id,
        'name' => 'Dr. Radiólogo',
    ]);
    $medico->assignRole('Facultativo');

    Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $medico->id,
        'fecha' => '2026-11-20',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    $this->actingAs($medico);

    // 1. Iniciar redirect OAuth2 guarda state en sesión
    $respRedirect = $this->get('/calendario/oauth/redirect');
    $respRedirect->assertRedirect();
    $state = session('gcal_oauth_state');
    expect($state)->not->toBeEmpty();

    // 2. Callback OAuth2 intercambia el código y guarda tokens cifrados
    $this->withSession(['gcal_oauth_state' => $state])
        ->get("/calendario/oauth/callback?code=fake-auth-code&state={$state}")
        ->assertRedirect(route('dashboard'));

    $medico->refresh();
    expect($medico->gcal_sync_enabled)->toBeTrue()
        ->and($medico->gcal_access_token)->toBe('ya29.fake-access-token')
        ->and($medico->gcal_refresh_token)->toBe('1//fake-refresh-token');

    // 3. Ejecutar el Job envía los eventos a Google Calendar API mediante Http client
    $job = new SyncGoogleCalendarJob($esp->id, [$medico->id], 11, 2026);
    $job->handle(app(GuardiaGeneratorService::class));

    Http::assertSent(fn($request) => str_contains($request->url(), 'googleapis.com/calendar/v3/calendars/primary/events/clinicoreguardia'));

    // 4. Desconectar OAuth2 limpia los tokens
    $this->delete('/calendario/oauth/disconnect')->assertRedirect();
    expect($medico->fresh()->gcal_access_token)->toBeNull()
        ->and($medico->fresh()->gcal_sync_enabled)->toBeFalse();
});
