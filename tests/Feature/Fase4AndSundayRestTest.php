<?php

use App\Jobs\SyncGoogleCalendarJob;
use App\Models\Ausencia;
use App\Models\Especialidad;
use App\Models\Festivo;
use App\Models\Guardia;
use App\Models\Hospital;
use App\Models\LimitacionGuardia;
use App\Models\User;
use App\Services\GuardiaGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['SuperAdmin', 'Jefe de Servicio', 'Tutor de Residentes', 'Facultativo', 'Facultativo Adjunto', 'Residente Mayor', 'Residente'] as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }
});

test('F4-01: AusenciaController::store valida especialidad_id y colectivo, y destroy/destroyLimitacion validan colectivo', function () {
    Queue::fake();

    $hospital = Hospital::create(['nombre' => 'Hospital Universitario']);
    $esp1 = Especialidad::create(['nombre' => 'Cardiología', 'codigo' => 'CAR', 'hospital_id' => $hospital->id]);
    $esp2 = Especialidad::create(['nombre' => 'Digestivo', 'codigo' => 'DIG', 'hospital_id' => $hospital->id]);

    $jefe1 = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => 'Dr. Jefe Cardio']);
    $jefe1->assignRole('Jefe de Servicio');

    $tutor1 = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => 'Dr. Tutor Cardio']);
    $tutor1->assignRole('Tutor de Residentes');

    $residenteMayor1 = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => 'Residente Mayor']);
    $residenteMayor1->assignRole('Residente Mayor');

    $adjunto1 = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => 'Dr. Adjunto Cardio']);
    $adjunto1->assignRole('Facultativo');

    $adjunto2 = User::factory()->create(['especialidad_id' => $esp2->id, 'name' => 'Dr. Adjunto Digestivo']);
    $adjunto2->assignRole('Facultativo');

    // 1. Jefe de Servicio de esp1 NO puede crear ausencia para un usuario de esp2
    $this->actingAs($jefe1)
        ->post('/ausencias', [
            'user_id' => $adjunto2->id,
            'tipo' => 'vacaciones',
            'fecha_inicio' => '2026-11-20',
            'fecha_fin' => '2026-11-22',
            'motivo' => 'Intento cross-tenant',
        ])
        ->assertForbidden();

    // 2. Tutor de Residentes NO puede crear ausencia para otro Adjunto de su misma especialidad
    $this->actingAs($tutor1)
        ->post('/ausencias', [
            'user_id' => $adjunto1->id,
            'tipo' => 'congreso',
            'fecha_inicio' => '2026-11-20',
            'fecha_fin' => '2026-11-22',
        ])
        ->assertForbidden();

    // 3. Residente Mayor NO puede borrar una guardia ni una limitación perteneciente a un Adjunto
    $guardiaAdjunto = Guardia::create([
        'especialidad_id' => $esp1->id,
        'user_id' => $adjunto1->id,
        'fecha' => '2026-11-25',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    $limitacionAdjunto = LimitacionGuardia::create([
        'especialidad_id' => $esp1->id,
        'user_id' => $adjunto1->id,
        'tipo' => 'dia_semana',
        'valor' => '1',
    ]);

    $this->actingAs($residenteMayor1)
        ->delete("/residentes/guardias/{$guardiaAdjunto->id}")
        ->assertForbidden();

    $this->actingAs($residenteMayor1)
        ->delete("/residentes/guardias/limitaciones/{$limitacionAdjunto->id}")
        ->assertForbidden();
});

test('F4-02: usar_plantilla_completa=false con seleccion vacia devuelve error y guardarGuardiaManual valida salientes cuando !forzar', function () {
    Queue::fake();

    $hospital = Hospital::create(['nombre' => 'Hospital Central']);
    $esp = Especialidad::create(['nombre' => 'Urgencias', 'codigo' => 'URG', 'hospital_id' => $hospital->id]);

    $jefe = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Jefe']);
    $jefe->assignRole('Jefe de Servicio');

    $adjunto = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Adjunto']);
    $adjunto->assignRole('Facultativo');

    $service = app(GuardiaGeneratorService::class);
    $resultado = $service->simularCuadrante($esp->id, GuardiaGeneratorService::COLECTIVO_ADJUNTOS, [
        'mes' => 11,
        'anio' => 2026,
        'personas_por_dia' => 1,
        'usar_plantilla_completa' => false,
        'medicos_incluidos' => [],
        'respetar_salientes' => true,
        'distancia_minima_dias' => 1,
        'agrupacion' => 'ninguna',
    ]);

    expect($resultado['ok'])->toBeFalse()
        ->and($resultado['error'])->toContain('No hay suficientes facultativos activos seleccionados');

    // Guardia previa el martes 2026-11-10
    Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $adjunto->id,
        'fecha' => '2026-11-10',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    // Intentar asignar manualmente el miércoles 2026-11-11 con forzar=false debe fallar por saliente
    $this->actingAs($jefe)
        ->post('/guardias/manual', [
            'user_id' => $adjunto->id,
            'fecha' => '2026-11-11',
            'tipo' => 'diaria_17h',
            'forzar' => false,
        ])
        ->assertSessionHasErrors('conflicto');
});

test('F4-03 y F4-04: Clasificacion unificada en Dashboard/CalendarioGlobal, bloqueo de reuso en Onboarding y proteccion division por cero en Personal', function () {
    $hospital = Hospital::create(['nombre' => 'Hospital Clínico']);
    $esp = Especialidad::create([
        'nombre' => 'Medicina Interna',
        'codigo' => 'MIN',
        'hospital_id' => $hospital->id,
        'limite_usuarios' => 0,
    ]);

    $jefe = User::factory()->create([
        'especialidad_id' => $esp->id,
        'name' => 'Dr. Jefe',
        'email_verified_at' => now(),
    ]);
    $jefe->assignRole('Jefe de Servicio');

    $anio = (int) now()->year;
    $mes = (int) now()->month;

    Festivo::create([
        'especialidad_id' => $esp->id,
        'fecha' => sprintf('%04d-%02d-15', $anio, $mes),
        'descripcion' => '[ESPECIAL] Día Patronal',
    ]);

    // Guardia guardada manualmente como diaria_17h en día [ESPECIAL] debe clasificarse como 24h [ESPECIAL]
    Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $jefe->id,
        'fecha' => sprintf('%04d-%02d-15', $anio, $mes),
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);

    $this->actingAs($jefe);

    $respDash = $this->get('/dashboard')->assertOk();
    $desglose = collect($respDash->viewData('page')['props']['desglose_anual'])->firstWhere('id', $jefe->id);
    expect($desglose['guardias_24h_festivo'])->toBe(1)
        ->and($desglose['guardias_24h_especial'])->toBe(1)
        ->and($desglose['horas_totales'])->toBe(24);

    $respCal = $this->get("/calendario-completo?mes={$mes}&anio={$anio}")->assertOk();
    $eventoGuardia = collect($respCal->viewData('page')['props']['eventos'])->firstWhere('tipo', 'GUARDIA');
    expect($eventoGuardia['detalle'])->toContain('[ESPECIAL]')
        ->and($eventoGuardia['horas'])->toBe(24);

    // PersonalController::index() no falla con DivisionByZeroError cuando limite_usuarios = 0
    $respPersonal = $this->get('/personal')->assertOk();
    expect($respPersonal->viewData('page')['props']['metricas_plan']['porcentaje'])->toBe(100);

    // OnboardingController::store() rechaza reutilizar el enlace firmado si email_verified_at !== null
    $signedUrl = URL::temporarySignedRoute('onboarding.password', now()->addHours(24), ['user' => $jefe->id]);
    $this->post($signedUrl, [
        'password' => 'NuevaClave123!',
        'password_confirmation' => 'NuevaClave123!',
    ])->assertForbidden();
});

test('F4-05: Facultativo o Residente estandar puede verificar conflictos de sus propias guardias y SyncGoogleCalendarJob se despacha en operaciones manuales', function () {
    Queue::fake();

    $hospital = Hospital::create(['nombre' => 'Hospital Sur']);
    $esp = Especialidad::create(['nombre' => 'Pediatría', 'codigo' => 'PED', 'hospital_id' => $hospital->id]);

    $jefe = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Jefe']);
    $jefe->assignRole('Jefe de Servicio');

    $m1 = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Adjunto 1']);
    $m1->assignRole('Facultativo');

    $m2 = User::factory()->create(['especialidad_id' => $esp->id, 'name' => 'Dr. Adjunto 2']);
    $m2->assignRole('Facultativo');

    $g1 = Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $m1->id,
        'fecha' => '2026-11-18',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    $g2 = Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $m2->id,
        'fecha' => '2026-11-24',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    // m1 (sin rol de Jefe) puede verificar conflictos en modo permuta para su propia guardia g1
    $this->actingAs($m1)
        ->postJson('/guardias/verificar-conflicto', [
            'modo' => 'permuta',
            'origen_id' => $g1->id,
            'destino_id' => $g2->id,
        ])
        ->assertOk()
        ->assertJsonPath('valido', true);

    // Operaciones manuales despachan SyncGoogleCalendarJob
    $this->actingAs($jefe)
        ->post('/guardias/manual', [
            'user_id' => $m1->id,
            'fecha' => '2026-11-28',
            'tipo' => 'festivo_24h',
            'forzar' => true,
        ])
        ->assertRedirect();

    Queue::assertPushed(SyncGoogleCalendarJob::class);
});

test('Bloque B: Regla de traslado de saliente en domingo al lunes (dentro del mes, frontera 30/31 -> 1/2 y bloques s_d / v_s_d)', function () {
    Notification::fake();
    Queue::fake();

    $hospital = Hospital::create(['nombre' => 'Hospital General']);
    $esp = Especialidad::create(['nombre' => 'Anestesia', 'codigo' => 'ANE', 'hospital_id' => $hospital->id]);

    $medicos = collect();
    for ($i = 1; $i <= 8; $i++) {
        $m = User::factory()->create(['especialidad_id' => $esp->id, 'name' => "Dr. Anestesista {$i}"]);
        $m->assignRole('Facultativo');
        $medicos->push($m);
    }

    $mSabado = $medicos[0];
    $service = app(GuardiaGeneratorService::class);

    // 1. Guardia manual en sábado 2026-11-07 -> el lunes 2026-11-09 queda bloqueado por saliente trasladado
    $gSab = Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $mSabado->id,
        'fecha' => '2026-11-07', // Sábado
        'tipo' => 'festivo_24h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);

    // checkPermutaColisionOSaliente detecta el bloqueo del lunes 2026-11-09 con distancia_minima_dias = 1
    $msgSaliente = $service->checkPermutaColisionOSaliente($mSabado->id, '2026-11-09', 0, 1);
    expect($msgSaliente)->not->toBeNull()
        ->and($msgSaliente)->toContain('saliente trasladado al lunes');

    // evaluarConflictosEnTiempoReal devuelve el tipo 'saliente_lunes_por_sabado'
    $diag = $service->evaluarConflictosEnTiempoReal($esp->id, GuardiaGeneratorService::COLECTIVO_ADJUNTOS, [
        'modo' => 'asignacion',
        'user_id' => $mSabado->id,
        'fecha' => '2026-11-09',
        'distancia_minima_dias' => 1,
    ]);
    expect($diag['valido'])->toBeFalse()
        ->and(collect($diag['conflictos'])->pluck('tipo')->all())->toContain('saliente_lunes_por_sabado');

    // 2. Frontera de mes: Sábado 31 de octubre de 2026 (2026-10-31) bloquea el Lunes 2 de noviembre de 2026 (2026-11-02)
    $mFrontera31 = $medicos[1];
    Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $mFrontera31->id,
        'fecha' => '2026-10-31', // Sábado 31
        'tipo' => 'festivo_24h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);

    // Generación de noviembre 2026 con agrupación s_d
    $errorNov = $service->generarCuadrante($esp->id, GuardiaGeneratorService::COLECTIVO_ADJUNTOS, [
        'mes' => 11,
        'anio' => 2026,
        'personas_por_dia' => 1,
        'usar_plantilla_completa' => true,
        'respetar_salientes' => true,
        'distancia_minima_dias' => 1,
        'proteger_vispera_vacaciones' => true,
        'agrupacion' => 's_d',
    ]);
    expect($errorNov)->toBeNull();

    // Verificar que mFrontera31 (que hizo el sábado 31/10) NO tiene guardia el lunes 02/11
    $guardiaLunes2Nov = Guardia::withoutGlobalScopes()
        ->where('especialidad_id', $esp->id)
        ->whereDate('fecha', '2026-11-02')
        ->first();
    expect($guardiaLunes2Nov)->not->toBeNull()
        ->and($guardiaLunes2Nov->user_id)->not->toBe($mFrontera31->id);

    // Verificar que para TODOS los sábados de noviembre 2026, quien hace guardia el sábado NO hace guardia el lunes siguiente
    $sabadosNov = ['2026-11-07', '2026-11-14', '2026-11-21', '2026-11-28'];
    foreach ($sabadosNov as $fSabStr) {
        $fLunStr = \Carbon\Carbon::parse($fSabStr)->addDays(2)->format('Y-m-d');
        $gSabDia = Guardia::withoutGlobalScopes()->where('especialidad_id', $esp->id)->whereDate('fecha', $fSabStr)->first();
        $gLunDia = Guardia::withoutGlobalScopes()->where('especialidad_id', $esp->id)->whereDate('fecha', $fLunStr)->first();

        expect($gSabDia)->not->toBeNull()
            ->and($gLunDia)->not->toBeNull()
            ->and($gLunDia->user_id)->not->toBe($gSabDia->user_id);
    }

    // 3. Frontera de mes cuando el sábado es día 30 (2026-05-30) y el lunes es día 1 (2026-06-01)
    $mFrontera30 = $medicos[2];
    Guardia::create([
        'especialidad_id' => $esp->id,
        'user_id' => $mFrontera30->id,
        'fecha' => '2026-05-30', // Sábado 30 de mayo
        'tipo' => 'festivo_24h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);

    $errorJun = $service->generarCuadrante($esp->id, GuardiaGeneratorService::COLECTIVO_ADJUNTOS, [
        'mes' => 6,
        'anio' => 2026,
        'personas_por_dia' => 1,
        'usar_plantilla_completa' => true,
        'respetar_salientes' => true,
        'distancia_minima_dias' => 1,
        'proteger_vispera_vacaciones' => true,
        'agrupacion' => 'v_s_d',
    ]);
    expect($errorJun)->toBeNull();

    $guardiaLunes1Jun = Guardia::withoutGlobalScopes()
        ->where('especialidad_id', $esp->id)
        ->whereDate('fecha', '2026-06-01')
        ->first();
    expect($guardiaLunes1Jun)->not->toBeNull()
        ->and($guardiaLunes1Jun->user_id)->not->toBe($mFrontera30->id);

    // Verificar que obtenerDatosIndex expone salientes_lunes incluyendo la frontera del 01/06/2026
    $datosJun = $service->obtenerDatosIndex($esp->id, 6, 2026, GuardiaGeneratorService::COLECTIVO_ADJUNTOS);
    $salientesLunesJun = collect($datosJun['salientes_lunes']);
    expect($salientesLunesJun->where('fecha', '2026-06-01')->where('user_id', $mFrontera30->id))->not->toBeEmpty();
});
