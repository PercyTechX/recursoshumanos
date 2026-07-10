<?php

use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EmpleadoController;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Portal del trabajador (autoservicio) — cualquier usuario autenticado;
// el componente muestra solo los datos del empleado vinculado.
Route::view('mi-espacio', 'portal.index')
    ->middleware(['auth'])
    ->name('portal.index');

// Cerrar sesión (usado por el botón del sidebar)
Route::post('logout', function (Logout $logout) {
    $logout();

    return redirect('/');
})->middleware('auth')->name('logout');

// Acceso por PERMISO de cada módulo (configurable desde "Roles y accesos").
// SuperAdmin siempre pasa (role_or_permission incluye el rol SuperAdmin).
Route::middleware('auth')->group(function () {
    // Roles y accesos (solo SuperAdmin)
    Route::middleware('role:SuperAdmin')->group(function () {
        Route::view('roles', 'roles.index')->name('roles.index');
    });

    Route::middleware('role_or_permission:SuperAdmin|usuarios.ver')->group(function () {
        Route::view('usuarios', 'usuarios.index')->name('usuarios.index');
    });

    Route::middleware('role_or_permission:SuperAdmin|clientes.ver')->group(function () {
        Route::view('clientes', 'clientes.index')->name('clientes.index');
        Route::get('clientes/{cliente}/sucursales', fn (\App\Models\Cliente $cliente) => view('clientes.sucursales', compact('cliente')))
            ->name('clientes.sucursales');
        Route::view('sedes', 'sedes.index')->name('sedes.index');
    });

    Route::middleware('role_or_permission:SuperAdmin|empleados.ver')->group(function () {
        Route::view('empleados', 'empleados.index')->name('empleados.index');
        Route::get('empleados/exportar', [EmpleadoController::class, 'exportar'])->name('empleados.exportar');
        Route::get('empleados/{empleado}/hoja-ruta', fn (\App\Models\Empleado $empleado) => view('empleados.hoja-ruta', compact('empleado')))
            ->name('empleados.hoja-ruta');
        Route::get('empleados/{empleado}', fn (\App\Models\Empleado $empleado) => view('empleados.show', compact('empleado')))
            ->name('empleados.show');
    });

    Route::middleware('role_or_permission:SuperAdmin|documentos.ver')->group(function () {
        Route::view('documentos', 'documentos.index')->name('documentos.index');
        Route::get('documentos/exportar', [DocumentoController::class, 'exportar'])->name('documentos.exportar');
    });

    Route::middleware('role_or_permission:SuperAdmin|documentos_compartidos.ver')->group(function () {
        Route::view('documentos-compartidos', 'documentos-compartidos.index')->name('documentos-compartidos.index');
    });

    Route::middleware('role_or_permission:SuperAdmin|activos.ver')->group(function () {
        Route::view('activos', 'activos.index')->name('activos.index');
    });

    Route::middleware('role_or_permission:SuperAdmin|vacaciones.ver')->group(function () {
        Route::view('vacaciones', 'vacaciones.index')->name('vacaciones.index');
    });

    Route::middleware('role_or_permission:SuperAdmin|ausencias.ver')->group(function () {
        Route::view('ausencias', 'ausencias.index')->name('ausencias.index');
    });

    Route::middleware('role_or_permission:SuperAdmin|descuentos.ver')->group(function () {
        Route::view('descuentos', 'descuentos.index')->name('descuentos.index');
    });
});

/*
 | Instalación sin SSH: corre migraciones + catálogos una sola vez.
 | Protegida por APP_SETUP_TOKEN (en .env). Si el token está vacío → 404.
 | Tras usarla, vaciar APP_SETUP_TOKEN para que vuelva a responder 404.
 */
Route::get('_setup/{token}', function (string $token) {
    $esperado = config('app.setup_token');
    abort_unless(is_string($esperado) && $esperado !== '' && hash_equals($esperado, $token), 404);

    Artisan::call('migrate', ['--force' => true]);
    $salida = Artisan::output();

    Artisan::call('db:seed', ['--class' => \Database\Seeders\CatalogoSeeder::class, '--force' => true]);
    $salida .= Artisan::output();

    return response('<pre style="font:14px/1.5 monospace;padding:16px">'.e($salida)."\n\n".
        'LISTO. Ahora vacía APP_SETUP_TOKEN en el .env y vuelve a desplegar.</pre>');
})->name('setup');

require __DIR__.'/auth.php';
