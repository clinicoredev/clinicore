<?php

use App\Exports\GuardiasExport;
use App\Models\Ausencia;
use App\Models\Especialidad;
use App\Models\Festivo;
use App\Models\Guardia;
use App\Models\Hospital;
use App\Models\User;
use App\Services\GuardiaGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['SuperAdmin', 'Jefe de Servicio', 'Tutor de Residentes', 'Facultativo Adjunto', 'Residente Mayor', 'Residente'] as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }
});

test('F2-01 y F2-02: GuardiaGeneratorService respeta frontera > 5 dias (anterior y siguiente) y guardias manuales de medicos excluidos', function () {
    $hospital = Hospital::create(['nombre' => 'Hospital Central']);
    $especialidad = Especialidad::create(['nombre' => 'Cardiología', 'codigo' => 'CARD', 'hospital_id' => $hospital->id, 'limite_usuarios' => 20]);

    $medicos = collect();
    for ($i = 1; $i <= 12; $i++) {
        $m = User::factory()->create(['especialidad_id' => $especialidad->id, 'name' => "Dr. Medico {$i}"]);
        $m->assignRole('Facultativo Adjunto');
        $medicos->push($m);
    }

    $m1 = $medicos[0];
    $m2 = $medicos[1];

    $mExcluido = User::factory()->create(['especialidad_id' => $especialidad->id, 'name' => 'Dr. Excluido']);
    $mExcluido->assignRole('Facultativo Adjunto');

    // El médico excluido tiene una guardia manual el día 2026-06-10
    Guardia::create([
        'especialidad_id' => $especialidad->id,
        'user_id' => $mExcluido->id,
        'fecha' => '2026-06-10',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);

    // m1 tiene guardia el 2026-05-26 (a 6 días del 2026-06-01, prueba frontera anterior > 5 días)
    Guardia::create([
        'especialidad_id' => $especialidad->id,
        'user_id' => $m1->id,
        'fecha' => '2026-05-26',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);

    // m2 tiene guardia el 2026-07-06 (a 6 días del 2026-06-30, prueba frontera mes siguiente > 5 días)
    Guardia::create([
        'especialidad_id' => $especialidad->id,
        'user_id' => $m2->id,
        'fecha' => '2026-07-06',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);

    $service = app(GuardiaGeneratorService::class);
    $error = $service->generarCuadrante($especialidad->id, GuardiaGeneratorService::COLECTIVO_ADJUNTOS, [
        'mes' => 6,
        'anio' => 2026,
        'personas_por_dia' => 1,
        'usar_plantilla_completa' => false,
        'medicos_incluidos' => $medicos->pluck('id')->all(),
        'respetar_salientes' => true,
        'distancia_minima_dias' => 6,
        'proteger_vispera_vacaciones' => true,
        'agrupacion' => 'ninguna',
    ]);

    expect($error)->toBeNull();

    // El día 2026-06-10 ya estaba cubierto por la manual del médico excluido, no debe duplicarse
    $guardiasDia10 = Guardia::withoutGlobalScopes()
        ->where('especialidad_id', $especialidad->id)
        ->whereDate('fecha', '2026-06-10')
        ->get();
    expect($guardiasDia10)->toHaveCount(1)
        ->and($guardiasDia10->first()->user_id)->toBe($mExcluido->id);

    // m1 no puede tener guardia el 2026-06-01 por la frontera de 6 días con 2026-05-26
    $guardiaDia1 = Guardia::withoutGlobalScopes()
        ->where('especialidad_id', $especialidad->id)
        ->whereDate('fecha', '2026-06-01')
        ->first();
    expect($guardiaDia1)->not->toBeNull()
        ->and($guardiaDia1->user_id)->not->toBe($m1->id);

    // m2 no puede tener guardia el 2026-06-30 por la frontera de 6 días con 2026-07-06
    $guardiaDia30 = Guardia::withoutGlobalScopes()
        ->where('especialidad_id', $especialidad->id)
        ->whereDate('fecha', '2026-06-30')
        ->first();
    expect($guardiaDia30)->not->toBeNull()
        ->and($guardiaDia30->user_id)->not->toBe($m2->id);
});

test('F2-03: Clasificacion unificada de 17h, 24h finde, 24h festivo y [ESPECIAL] en Export, PDF e iCal', function () {
    $hospital = Hospital::create(['nombre' => 'Hospital Sur']);
    $especialidad = Especialidad::create(['nombre' => 'Pediatría', 'codigo' => 'PED', 'hospital_id' => $hospital->id]);

    $medico = User::factory()->create([
        'especialidad_id' => $especialidad->id,
        'name' => 'Dra. García',
        'calendar_token' => 'token-test-123456',
    ]);
    $medico->assignRole('Facultativo Adjunto');

    Festivo::create([
        'especialidad_id' => $especialidad->id,
        'fecha' => '2026-12-25',
        'descripcion' => '[ESPECIAL] Navidad',
    ]);

    $gEspecial = Guardia::create([
        'especialidad_id' => $especialidad->id,
        'user_id' => $medico->id,
        'fecha' => '2026-12-25',
        'tipo' => 'festivo_24h',
        'estado' => 'programada',
        'is_manual' => true,
    ]);

    $gLaborable = Guardia::create([
        'especialidad_id' => $especialidad->id,
        'user_id' => $medico->id,
        'fecha' => '2026-12-10',
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
        'is_manual' => false,
    ]);

    $export = new GuardiasExport($especialidad->id, 12, 2026, [$medico->id]);
    $filaEspecial = $export->map($gEspecial);
    $filaLaborable = $export->map($gLaborable);

    expect($filaEspecial[3])->toContain('24h (★ Especial)')
        ->and($filaEspecial[3])->toContain('Navidad')
        ->and($filaEspecial[4])->toBe('24h')
        ->and($filaLaborable[3])->toBe('17h (Diaria)')
        ->and($filaLaborable[4])->toBe('17h');

    $service = app(GuardiaGeneratorService::class);
    $datosPdf = $service->construirDatosReportePdf($especialidad->id, 12, 2026, GuardiaGeneratorService::COLECTIVO_ADJUNTOS);
    $eq = $datosPdf['equidad']->first();
    expect($eq->diarias_17h)->toBe(1)
        ->and($eq->especiales_24h)->toBe(1)
        ->and($eq->horas_totales)->toBe(17 + 24);
});

test('F2-04 y F2-06: Dashboard separa colectivos, habilita Tutor de Residentes y aplica EspecialidadScope', function () {
    $hospital = Hospital::create(['nombre' => 'Hospital Norte']);
    $esp1 = Especialidad::create(['nombre' => 'Urgencias', 'codigo' => 'URG', 'hospital_id' => $hospital->id]);
    $esp2 = Especialidad::create(['nombre' => 'Traumatología', 'codigo' => 'TRA', 'hospital_id' => $hospital->id]);

    $tutor = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => 'Dr. Tutor']);
    $tutor->assignRole('Tutor de Residentes');

    $residente = User::factory()->create(['especialidad_id' => $esp1->id, 'name' => 'Dra. Residente']);
    $residente->assignRole('Residente');

    $otroTenantUser = User::factory()->create(['especialidad_id' => $esp2->id, 'name' => 'Dr. Otro Tenant']);
    $otroTenantUser->assignRole('Facultativo Adjunto');

    $hoy = now()->format('Y-m-d');

    Guardia::withoutGlobalScopes()->create([
        'especialidad_id' => $esp1->id,
        'user_id' => $tutor->id,
        'fecha' => $hoy,
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    Guardia::withoutGlobalScopes()->create([
        'especialidad_id' => $esp1->id,
        'user_id' => $residente->id,
        'fecha' => $hoy,
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    Guardia::withoutGlobalScopes()->create([
        'especialidad_id' => $esp2->id,
        'user_id' => $otroTenantUser->id,
        'fecha' => $hoy,
        'tipo' => 'diaria_17h',
        'estado' => 'programada',
    ]);

    Ausencia::withoutGlobalScopes()->create([
        'especialidad_id' => $esp1->id,
        'user_id' => $residente->id,
        'tipo' => 'congreso',
        'fecha_inicio' => $hoy,
        'fecha_fin' => $hoy,
        'estado' => 'pendiente',
    ]);

    $this->actingAs($tutor);

    // El Global Scope BelongsToEspecialidad debe filtrar automáticamente las guardias de esp2
    expect(Guardia::count())->toBe(2);

    $response = $this->get('/dashboard');
    $response->assertOk();

    $page = $response->viewData('page')['props'];
    expect($page['permisos']['es_tutor'])->toBeTrue()
        ->and($page['permisos']['puede_revisar_ausencias'])->toBeTrue()
        ->and($page['kpis']['ausencias_pendientes'])->toBe(1)
        ->and($page['guardias_hoy']['adjuntos'])->toHaveCount(1)
        ->and($page['guardias_hoy']['residentes'])->toHaveCount(1);
});
