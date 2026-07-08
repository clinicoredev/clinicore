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

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

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

    // --- MÓDULO GUARDIAS ---
    Route::get('/guardias', [GuardiaController::class, 'index'])->name('guardias.index');
    Route::post('/guardias/limitaciones', [GuardiaController::class, 'storeLimitacion'])->name('guardias.limitaciones.store');
    Route::delete('/guardias/limitaciones/{limitacion}', [GuardiaController::class, 'destroyLimitacion'])->name('guardias.limitaciones.destroy');

    Route::get('/guardias/exportar/excel', [GuardiaController::class, 'exportarExcel'])->name('guardias.exportar.excel');
    Route::get('/guardias/exportar/pdf', [GuardiaController::class, 'exportarPdf'])->name('guardias.exportar.pdf');
    
    // Motor IA y Operaciones Fijas
    Route::post('/guardias/generar', [GuardiaController::class, 'generarAlgoritmo'])->name('guardias.generar');
    Route::post('/guardias/manual', [GuardiaController::class, 'storeManual'])->name('guardias.manual');
    Route::delete('/guardias/vaciar-mes', [GuardiaController::class, 'vaciarMes'])->name('guardias.vaciarMes');
    Route::post('/guardias/permutar', [GuardiaController::class, 'permutar'])->name('guardias.permutar');
    
    // Operaciones sobre una guardia específica (Comodín al final)
    Route::delete('/guardias/{guardia}', [GuardiaController::class, 'destroy'])->name('guardias.destroy');
});

require __DIR__.'/settings.php';