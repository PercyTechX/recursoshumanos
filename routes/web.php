<?php

use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EmpleadoController;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Cerrar sesión (usado por el botón del sidebar)
Route::post('logout', function (Logout $logout) {
    $logout();

    return redirect('/');
})->middleware('auth')->name('logout');

// Módulos RRHH (acceso: RRHH, Gerencia, Supervisor)
Route::middleware(['auth', 'role:RRHH|Gerencia|Supervisor'])->group(function () {
    Route::view('empleados', 'empleados.index')->name('empleados.index');
    Route::get('empleados/exportar', [EmpleadoController::class, 'exportar'])->name('empleados.exportar');
    Route::get('empleados/{empleado}/hoja-ruta', fn (\App\Models\Empleado $empleado) => view('empleados.hoja-ruta', compact('empleado')))
        ->name('empleados.hoja-ruta');
    Route::get('empleados/{empleado}', fn (\App\Models\Empleado $empleado) => view('empleados.show', compact('empleado')))
        ->name('empleados.show');

    Route::view('documentos', 'documentos.index')->name('documentos.index');
    Route::get('documentos/exportar', [DocumentoController::class, 'exportar'])->name('documentos.exportar');
    Route::view('documentos-compartidos', 'documentos-compartidos.index')->name('documentos-compartidos.index');

    Route::view('activos', 'activos.index')->name('activos.index');
});

// Descuentos — visible para Contador (y RRHH/Gerencia)
Route::middleware(['auth', 'role:Contador|RRHH|Gerencia'])->group(function () {
    Route::view('descuentos', 'descuentos.index')->name('descuentos.index');
});

require __DIR__.'/auth.php';
