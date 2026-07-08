<?php

use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EmpleadoController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Módulos RRHH (acceso: RRHH, Gerencia, Supervisor)
Route::middleware(['auth', 'role:RRHH|Gerencia|Supervisor'])->group(function () {
    Route::view('empleados', 'empleados.index')->name('empleados.index');
    Route::get('empleados/exportar', [EmpleadoController::class, 'exportar'])->name('empleados.exportar');

    Route::view('documentos', 'documentos.index')->name('documentos.index');
    Route::get('documentos/exportar', [DocumentoController::class, 'exportar'])->name('documentos.exportar');

    Route::view('activos', 'activos.index')->name('activos.index');
});

require __DIR__.'/auth.php';
