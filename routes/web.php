<?php

use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\PersonalController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AusenciaController;
use App\Http\Controllers\CalendarioGlobalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuardiaController;
use App\Http\Controllers\PermutaSolicitudController;
use App\Http\Controllers\ResidenteGuardiaController;
use App\Http\Controllers\FestivoController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

Route::get('/privacidad', fn () => Inertia::render('Legal/Privacidad'))->name('legal.privacidad');
Route::get('/terminos', fn () => Inertia::render('Legal/Terminos'))->name('legal.terminos');

// 1. RUTA PARA SALIR DEL MODO DIOS 
// (Obligatorio: debe estar FUERA del middleware 'role:SuperAdmin' porque cuando 
//  suplantas a un Facultativo, dejas de ser SuperAdmin temporalmente).
Route::post('/impersonate/leave', [TenantController::class, 'leaveImpersonate'])->name('impersonate.leave');

Route::get('/feed/calendario/{token}.ics', [CalendarFeedController::class, 'feed'])->name('calendar.feed');

// 2. GRUPO DEL SUPERADMIN
Route::middleware(['auth', 'role:SuperAdmin'])->prefix('admin')->group(function () {
    
    // Gestión de Tenants
    Route::get('/tenants', [TenantController::class, 'index'])->name('admin.tenants.index');
    Route::post('/tenants', [TenantController::class, 'store'])->name('admin.tenants.store');
    Route::put('/tenants/{id}', [TenantController::class, 'update'])->name('admin.tenants.update');
    Route::delete('/tenants/{id}', [TenantController::class, 'destroy'])->name('admin.tenants.destroy');

    // Gestión de Usuarios
    Route::post('/users', [TenantController::class, 'storeUser'])->name('admin.users.store');
    Route::put('/users/{id}', [TenantController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/users/{id}', [TenantController::class, 'destroyUser'])->name('admin.users.destroy');


    // MODO DIOS: ENTRAR 
    // (Asegúrate de que es un método POST y está DENTRO de este grupo admin)
    Route::post('/impersonate/{id}', [TenantController::class, 'impersonate'])->name('admin.impersonate');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/calendario-completo', [CalendarioGlobalController::class, 'index'])->name('calendariocompleto.index');
    Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    
    // Directorio de Personal
    Route::get('/personal', [PersonalController::class, 'index'])->name('personal.index');
    Route::post('/personal', [PersonalController::class, 'store'])->name('personal.store');
    Route::patch('/personal/{user}', [PersonalController::class, 'update'])->name('personal.update');
    Route::delete('/personal/{user}', [PersonalController::class, 'destroy'])->name('personal.destroy');

    // NUEVO MÓDULO AUSENCIAS:
    Route::get('/ausencias', [AusenciaController::class, 'index'])->name('ausencias.index');
    Route::post('/ausencias', [AusenciaController::class, 'store'])->name('ausencias.store');
    Route::patch('/ausencias/{ausencia}/resolver', [AusenciaController::class, 'resolver'])->name('ausencias.resolver');
    Route::patch('/ausencias/{ausencia}', [AusenciaController::class, 'update'])->name('ausencias.update');
    Route::delete('/ausencias/{ausencia}', [AusenciaController::class, 'destroy'])->name('ausencias.destroy');

    // MÓDULO SOLICITUDES DE INTERCAMBIO DE GUARDIAS Y BOLSA DE VACANTES (F3-04 / F5-01 / F6-05)
    Route::get('/permutas', [PermutaSolicitudController::class, 'index'])->name('permutas.index');
    Route::post('/permutas', [PermutaSolicitudController::class, 'store'])->name('permutas.store');
    Route::post('/permutas/vacantes', [PermutaSolicitudController::class, 'publicarVacante'])->name('permutas.vacantes.store');
    Route::patch('/permutas/{permuta}/postular-vacante', [PermutaSolicitudController::class, 'postularVacante'])->name('permutas.vacantes.postular');
    Route::patch('/permutas/{permuta}/responder-companero', [PermutaSolicitudController::class, 'responderCompanero'])->name('permutas.responderCompanero');
    Route::patch('/permutas/{permuta}/resolver', [PermutaSolicitudController::class, 'resolver'])->name('permutas.resolver');
    Route::delete('/permutas/{permuta}', [PermutaSolicitudController::class, 'destroy'])->name('permutas.destroy');

    // SINCRONIZACIÓN ASÍNCRONA Y OAUTH2 DE CALENDARIO (F3-05 / F5-05)
    Route::post('/calendario/sync-config', [CalendarFeedController::class, 'configurarSync'])->name('calendario.syncConfig');
    Route::get('/calendario/oauth/redirect', [CalendarFeedController::class, 'oauthRedirect'])->name('calendario.oauth.redirect');
    Route::get('/calendario/oauth/callback', [CalendarFeedController::class, 'oauthCallback'])->name('calendario.oauth.callback');
    Route::delete('/calendario/oauth/disconnect', [CalendarFeedController::class, 'oauthDisconnect'])->name('calendario.oauth.disconnect');

    // --- MÓDULO GUARDIAS ---
    Route::get('/guardias', [GuardiaController::class, 'index'])->name('guardias.index');
    Route::post('/guardias/limitaciones', [GuardiaController::class, 'storeLimitacion'])->name('guardias.limitaciones.store');
    Route::delete('/guardias/limitaciones/{limitacion}', [GuardiaController::class, 'destroyLimitacion'])->name('guardias.limitaciones.destroy');

    Route::get('/guardias/exportar/excel', [GuardiaController::class, 'exportarExcel'])->name('guardias.exportar.excel');
    Route::get('/guardias/exportar/pdf', [GuardiaController::class, 'exportarPdf'])->name('guardias.exportar.pdf');
    
    // Motor IA y Operaciones Fijas
    Route::post('/guardias/simular', [GuardiaController::class, 'simularAlgoritmo'])->name('guardias.simular');
    Route::post('/guardias/generar', [GuardiaController::class, 'generarAlgoritmo'])->name('guardias.generar');
    Route::post('/guardias/verificar-conflicto', [GuardiaController::class, 'verificarConflicto'])->name('guardias.verificarConflicto');
    Route::post('/guardias/manual', [GuardiaController::class, 'guardarGuardiaManual'])->name('guardias.manual');
    Route::delete('/guardias/vaciar-mes', [GuardiaController::class, 'vaciarMes'])->name('guardias.vaciarMes');
    Route::post('/guardias/permutar', [GuardiaController::class, 'permutar'])->name('guardias.permutar');

    // Gestión global de Festivos del Tenant
    Route::get('/festivos', [FestivoController::class, 'index'])->name('festivos.index');
    Route::post('/festivos', [FestivoController::class, 'store'])->name('festivos.store');
    Route::delete('/festivos/{festivo}', [FestivoController::class, 'destroy'])->name('festivos.destroy');
    Route::post('/festivos/precargar', [FestivoController::class, 'precargarNacionales'])->name('festivos.precargar');
    
    // Operaciones sobre una guardia específica (Comodín al final)
    Route::delete('/guardias/{guardia}', [GuardiaController::class, 'destroy'])->name('guardias.destroy');

    Route::prefix('residentes')->middleware(['auth'])->group(function () {
        Route::get('/guardias', [ResidenteGuardiaController::class, 'index'])->name('residentes.guardias.index');
        Route::post('/guardias/simular', [ResidenteGuardiaController::class, 'simularAlgoritmo'])->name('residentes.guardias.simular');
        Route::post('/guardias/generar', [ResidenteGuardiaController::class, 'generarAlgoritmo']);
        Route::post('/guardias/verificar-conflicto', [ResidenteGuardiaController::class, 'verificarConflicto'])->name('residentes.guardias.verificarConflicto');
        Route::post('/guardias/manual', [ResidenteGuardiaController::class, 'guardarGuardiaManual']);
        Route::post('/guardias/permutar', [ResidenteGuardiaController::class, 'permutar']);
        Route::delete('/guardias/vaciar-mes', [ResidenteGuardiaController::class, 'vaciarMes']);
        Route::delete('/guardias/{guardia}', [ResidenteGuardiaController::class, 'destroy']);
        Route::get('/guardias/exportar/excel', [ResidenteGuardiaController::class, 'exportarExcel']);
        Route::get('/guardias/exportar/pdf', [ResidenteGuardiaController::class, 'exportarPdf']);
        
        // Rutas de limitaciones para residentes
        Route::post('/guardias/limitaciones', [ResidenteGuardiaController::class, 'storeLimitacion']);
        Route::delete('/guardias/limitaciones/{limitacion}', [ResidenteGuardiaController::class, 'destroyLimitacion']);
    });
});

Route::get('/bienvenido/{user}', [\App\Http\Controllers\OnboardingController::class, 'show'])
    ->name('onboarding.password');

Route::post('/bienvenido/{user}', [\App\Http\Controllers\OnboardingController::class, 'store'])
    ->name('onboarding.password.store');

Route::post('/notificaciones/marcar-leidas', function (\Illuminate\Http\Request $request) {
    // Busca todas las notificaciones no leídas del usuario y las marca como leídas
    $request->user()->unreadNotifications->markAsRead();
    // Devuelve al usuario a donde estaba sin recargar
    return back();
})->middleware('auth');

require __DIR__.'/settings.php';