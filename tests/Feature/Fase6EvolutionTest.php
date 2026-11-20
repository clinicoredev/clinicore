<?php

use App\Exports\GuardiasExport;
use App\Jobs\SyncGoogleCalendarJob;
use App\Models\Especialidad;
use App\Models\Festivo;
use App\Models\Guardia;
use App\Models\Hospital;
use App\Models\SolicitudPermuta;
use App\Models\User;
use App\Notifications\PermutaPropuestaNotification;
use App\Notifications\PermutaResueltaNotification;
use App\Notifications\TurnoVacanteNotification;
use App\Services\GuardiaGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
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

test('F6-01 y F6-04: SyncGoogleCalendarJob hace upsert (PUT 404 -> POST), DELETE de turnos borrados y gestiona tokens revocados (401/403) + validacion de config OAuth2', function () {
    $hospital = Hospital::create(['nombre' => 'Hospital Universitario']);
    $esp = Especialidad::create(['nombre' => 'Medicina Interna', 'codigo' => 'MIN', 'hospital_id' => $hospital->id]);

    $medico = User::factory()->create([
        'especialidad_id' => $esp->id,
        'name' => 'Dr. Sincronizado',
        'gcal_sync_enabled' => true,
        'gcal_calendar_id' => 'primary',
        'gcal_access_token' => 'ya29.valid-token',
        'gcal_refresh_token' => '1//valid-refresh',
        'gcal_token_expires_at' => now()->addHour(),
    ]);
    $medico->assignRole('Facultativo');

    $guardiaNueva = Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $medico->id,
        'fecha' => '2026-11-20',
        'tipo' => 'diaria_17h',
        'modalidad' => 'localizada',
        'estado' => 'programada',
    ]);

    $forzar401 = false;

    Http::fake(function (HttpClientRequest $request) use ($guardiaNueva, &$forzar401) {
        if ($forzar401) {
            return Http::response(['error' => 'invalid_credentials'], 401);
        }

        $url = $request->url();
        $method = $request->method();

        // El evento borrado (ID 999) recibe DELETE
        if ($method === 'DELETE' && str_contains($url, 'events/clinicoreguardia999')) {
            return Http::response([], 204);
        }

        // El evento nuevo devuelve 404 en PUT para forzar fallback a POST
        if ($method === 'PUT' && str_contains($url, 'events/clinicoreguardia' . $guardiaNueva->id)) {
            return Http::response(['error' => ['code' => 404, 'message' => 'Not Found']], 404);
        }

        // El fallback POST crea el evento con éxito
        if ($method === 'POST' && str_ends_with($url, '/calendars/primary/events')) {
            return Http::response(['id' => 'clinicoreguardia' . $guardiaNueva->id, 'status' => 'confirmed'], 200);
        }

        return Http::response([], 200);
    });

    $job = new SyncGoogleCalendarJob($esp->id, [$medico->id], 11, 2026, [999]);
    $job->handle(app(GuardiaGeneratorService::class));

    Http::assertSent(fn(HttpClientRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'events/clinicoreguardia999'));
    Http::assertSent(fn(HttpClientRequest $r) => $r->method() === 'PUT' && str_contains($r->url(), 'events/clinicoreguardia' . $guardiaNueva->id));
    Http::assertSent(fn(HttpClientRequest $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/calendars/primary/events'));

    // Si Google responde 401 Unauthorized, el job desactiva gcal_sync_enabled y limpia tokens
    $forzar401 = true;

    $jobRevocado = new SyncGoogleCalendarJob($esp->id, [$medico->id], 11, 2026);
    $jobRevocado->handle(app(GuardiaGeneratorService::class));

    $medico->refresh();
    expect($medico->gcal_sync_enabled)->toBeFalse()
        ->and($medico->gcal_access_token)->toBeNull()
        ->and($medico->gcal_refresh_token)->toBeNull();

    // Si faltan credenciales GOOGLE_CLIENT_ID / SECRET, oauthRedirect no usa placeholders y redirige con error
    config(['services.google.client_id' => '', 'services.google.client_secret' => '']);
    $this->actingAs($medico)
        ->get('/calendario/oauth/redirect')
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('gcal');
});

test('F6-02 y F6-06: Modalidad desplegable (Presencial / Localizada) cuenta las horas igual en todos los reportes y GuardiasExport genera resumen de nomina', function () {
    $hospital = Hospital::create(['nombre' => 'Hospital Central']);
    $esp = Especialidad::create(['nombre' => 'Cirugía', 'codigo' => 'CIR', 'hospital_id' => $hospital->id]);

    $medico = User::factory()->create([
        'especialidad_id' => $esp->id,
        'name' => 'Dra. Cirujana',
        'porcentaje_jornada' => 100,
    ]);
    $medico->assignRole('Facultativo');

    $gPresencial = Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $medico->id,
        'fecha' => '2026-11-10', // Martes 17h
        'tipo' => 'diaria_17h',
        'modalidad' => 'presencial',
        'estado' => 'programada',
    ]);

    $gLocalizada = Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $medico->id,
        'fecha' => '2026-11-14', // Sábado 24h
        'tipo' => 'festivo_24h',
        'modalidad' => 'localizada',
        'estado' => 'programada',
    ]);

    $service = app(GuardiaGeneratorService::class);
    $infoPres = $service->clasificarTipologiaGuardia($gPresencial, []);
    $infoLoc = $service->clasificarTipologiaGuardia($gLocalizada, []);

    // Ambas modalidades cuentan el 100% de sus horas (17h y 24h), diferenciando la etiqueta de modalidad
    expect($infoPres['horas'])->toBe(17)
        ->and($infoPres['horas_efectivas_computo'])->toBe(17)
        ->and($infoPres['etiqueta_modalidad'])->toBe('Presencial')
        ->and($infoLoc['horas'])->toBe(24)
        ->and($infoLoc['horas_efectivas_computo'])->toBe(24)
        ->and($infoLoc['etiqueta_modalidad'])->toBe('Localizada')
        ->and($infoLoc['etiqueta'])->toContain('[Localizada]');

    // Exportación Excel: incluye columna de Modalidad y hoja de Resumen RRHH / Nómina
    $export = new GuardiasExport($esp->id, 11, 2026, [$medico->id]);
    $filaLoc = $export->map($gLocalizada);
    expect($filaLoc[4])->toBe('24h')
        ->and($filaLoc[6])->toBe('Localizada')
        ->and($filaLoc[7])->toBe('24h');

    $resumenNomina = $export->resumenNomina();
    expect($resumenNomina)->toHaveCount(1)
        ->and($resumenNomina[0]['turnos_presenciales'])->toBe(1)
        ->and($resumenNomina[0]['turnos_localizados'])->toBe(1)
        ->and($resumenNomina[0]['horas_totales_computo'])->toBe(41);

    // Reporte PDF: incluye desglose de presenciales/localizadas y suma 41h
    $datosPdf = $service->construirDatosReportePdf($esp->id, 11, 2026, GuardiaGeneratorService::COLECTIVO_ADJUNTOS);
    $eqPdf = $datosPdf['equidad']->first();
    expect($eqPdf->presenciales)->toBe(1)
        ->and($eqPdf->localizadas)->toBe(1)
        ->and($eqPdf->horas_totales)->toBe(41);
});

test('F6-03 y F6-06: Aviso 48h/semana estrictamente informativo considerando semana ISO del mes anterior y rotacion interanual de festivos [ESPECIAL]', function () {
    Notification::fake();
    Queue::fake();

    $hospital = Hospital::create(['nombre' => 'Hospital Clínico']);
    $esp = Especialidad::create(['nombre' => 'Anestesiología', 'codigo' => 'ANE', 'hospital_id' => $hospital->id]);

    $m1 = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Pringado Navidad 2025']);
    $m1->assignRole('Facultativo');

    $m2 = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dra. Libre Navidad 2025']);
    $m2->assignRole('Facultativo');

    for ($i = 3; $i <= 6; $i++) {
        $m = User::factory()->create(['especialidad_id' => $esp->id, 'name' => "Dr. Adjunto {$i}"]);
        $m->assignRole('Facultativo');
    }

    // F6-06: m1 hizo el festivo [ESPECIAL] de Navidad en 2025 (2025-12-25)
    Festivo::create([
        'especialidad_id' => $esp->id,
        'fecha' => '2025-12-25',
        'descripcion' => '[ESPECIAL] Navidad 2025',
    ]);
    Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $m1->id,
        'fecha' => '2025-12-25',
        'tipo' => 'festivo_24h',
        'estado' => 'programada',
    ]);

    // En diciembre 2026 solo m1 y m2 están seleccionados para competir por el [ESPECIAL] 2026-12-25
    Festivo::create([
        'especialidad_id' => $esp->id,
        'fecha' => '2026-12-25',
        'descripcion' => '[ESPECIAL] Navidad 2026',
    ]);

    $service = app(GuardiaGeneratorService::class);

    // F6-03: 1 de mayo de 2026 es Viernes (Semana ISO 2026-W18: Lunes 27/04 al Domingo 03/05).
    // Si m1 hizo Lunes 27/04 (17h) y Miércoles 29/04 (17h) = 34h en abril, y se fija manualmente el Viernes 01/05 (17h),
    // la semana ISO 2026-W18 suma 34 + 17 = 51h (>48h), emitiendo alerta informativa SIN bloquear el simulador.
    Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $m1->id,
        'fecha' => '2026-04-27',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);
    Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $m1->id,
        'fecha' => '2026-04-29',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);
    Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $m1->id,
        'fecha' => '2026-05-01',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);

    $simMayo = $service->simularCuadrante($esp->id, GuardiaGeneratorService::COLECTIVO_ADJUNTOS, [
        'mes' => 5,
        'anio' => 2026,
        'personas_por_dia' => 1,
        'usar_plantilla_completa' => true,
        'respetar_salientes' => true,
        'distancia_minima_dias' => 1,
        'agrupacion' => 'ninguna',
        'modalidad' => 'localizada',
    ]);

    expect($simMayo['ok'])->toBeTrue();
    $alertaW18 = collect($simMayo['alertas_tope_legal'])
        ->first(fn(array $a): bool => $a['user_id'] === $m1->id && $a['semana'] === '2026-W18');
    expect($alertaW18)->not->toBeNull()
        ->and($alertaW18['horas_semana'])->toBeGreaterThan(48);

    // Verificar que los turnos generados por el motor llevan la modalidad seleccionada ('localizada')
    $turnoIaMayo = collect($simMayo['guardias_simuladas'])->firstWhere('is_manual', false);
    expect($turnoIaMayo['modalidad'])->toBe('localizada');
});

test('F6-05 y F6-07: Bolsa de cobertura de turnos vacantes con validacion de salientes, aislamiento de colectivo y panel de observabilidad en Auditoria/Dashboard', function () {
    Notification::fake();
    Queue::fake();

    $hospital = Hospital::create(['nombre' => 'Hospital General']);
    $esp = Especialidad::create(['nombre' => 'Urgencias', 'codigo' => 'URG', 'hospital_id' => $hospital->id]);

    $jefe = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Jefe Urgencias']);
    $jefe->assignRole('Jefe de Servicio');

    $titular = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Titular Baja']);
    $titular->assignRole('Facultativo');

    $voluntarioBloqueado = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. En Saliente']);
    $voluntarioBloqueado->assignRole('Facultativo');

    $voluntarioLibre = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dra. Voluntaria']);
    $voluntarioLibre->assignRole('Facultativo');

    $residente = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Residente MIR']);
    $residente->assignRole('Residente');

    // Fecha futura para la vacante: miércoles dentro de 2 semanas
    $fechaVacante = now()->addDays(14)->startOfDay();
    $fechaVispera = $fechaVacante->copy()->subDay();

    $guardiaVacante = Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $titular->id,
        'fecha' => $fechaVacante->format('Y-m-d'),
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    // voluntarioBloqueado tiene guardia el día anterior (saliente obligatorio)
    Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $voluntarioBloqueado->id,
        'fecha' => $fechaVispera->format('Y-m-d'),
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    // 1. El titular publica su turno en la bolsa de vacantes
    $this->actingAs($titular)
        ->post('/permutas/vacantes', [
            'guardia_origen_id' => $guardiaVacante->id,
            'motivo' => 'Baja médica sobrevenida',
        ])
        ->assertRedirect();

    $vacante = SolicitudPermuta::first();
    expect($vacante)->not->toBeNull()
        ->and($vacante->tipo_solicitud)->toBe('cobertura_vacante')
        ->and($vacante->estado)->toBe('abierta_bolsa')
        ->and($vacante->guardia_destino_id)->toBeNull();

    // Notifica a los adjuntos del servicio pero NO al residente ni al propio titular
    Notification::assertSentTo($voluntarioLibre, TurnoVacanteNotification::class);
    Notification::assertNotSentTo($residente, TurnoVacanteNotification::class);
    Notification::assertNotSentTo($titular, TurnoVacanteNotification::class);

    // 2. Un residente NO puede postularse a una vacante de adjuntos (403)
    $this->actingAs($residente)
        ->patch("/permutas/{$vacante->id}/postular-vacante")
        ->assertForbidden();

    // 3. El voluntario con colisión de saliente recibe error de validación clínica
    $this->actingAs($voluntarioBloqueado)
        ->patch("/permutas/{$vacante->id}/postular-vacante")
        ->assertSessionHasErrors('conflicto');

    // 4. La voluntaria libre se postula con éxito -> pasa a pendiente_aprobacion y notifica al Jefe
    $this->actingAs($voluntarioLibre)
        ->patch("/permutas/{$vacante->id}/postular-vacante")
        ->assertRedirect();

    expect($vacante->fresh()->estado)->toBe('pendiente_aprobacion')
        ->and($vacante->fresh()->destinatario_id)->toBe($voluntarioLibre->id);

    Notification::assertSentTo($jefe, PermutaPropuestaNotification::class);

    // 5. El Jefe aprueba la cobertura -> el turno pasa a la voluntaria libre
    $this->actingAs($jefe)
        ->patch("/permutas/{$vacante->id}/resolver", ['estado' => 'aprobada'])
        ->assertRedirect();

    expect($vacante->fresh()->estado)->toBe('aprobada')
        ->and($guardiaVacante->fresh()->user_id)->toBe($voluntarioLibre->id);

    Notification::assertSentTo([$titular, $voluntarioLibre], PermutaResueltaNotification::class);
    Queue::assertPushed(SyncGoogleCalendarJob::class);

    // 6. Verificar métricas trimestrales 48h en Dashboard y panel de observabilidad en Auditoría (F6-07)
    $respDash = $this->actingAs($jefe)->get('/dashboard')->assertOk();
    $propsDash = $respDash->viewData('page')['props'];
    expect($propsDash)->toHaveKey('cumplimiento_trimestral_48h')
        ->and($propsDash['gcal_sync'])->toHaveKey('oauth_connected');

    $respAudit = $this->actingAs($jefe)->get('/auditoria')->assertOk();
    $propsAudit = $respAudit->viewData('page')['props'];
    expect($propsAudit)->toHaveKey('observabilidad')
        ->and($propsAudit['observabilidad'])->toHaveKeys([
            'syncs_exitosos',
            'syncs_fallidos',
            'usuarios_sync_activos',
            'jobs_en_cola',
            'jobs_fallidos',
        ]);
});
