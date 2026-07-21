<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TableroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_rrhh_ve_los_kpis(): void
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Empleados activos')
            ->assertSee('Tickets abiertos');
    }

    public function test_muestra_cumpleanos_del_mes_y_resalta_hoy(): void
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');

        // Cumple HOY (mismo día y mes, otro año) → debe resaltarse
        Empleado::create([
            'numero_documento' => '20202020', 'nombres' => 'Ana', 'apellidos' => 'Torres',
            'situacion' => 'activo', 'fecha_nacimiento' => today()->copy()->subYears(30)->format('Y-m-d'),
        ]);
        // Cumple este mes pero otro día → aparece sin resaltar
        Empleado::create([
            'numero_documento' => '30303030', 'nombres' => 'Beto', 'apellidos' => 'Ramos',
            'situacion' => 'activo', 'fecha_nacimiento' => today()->copy()->startOfMonth()->subYears(25)->format('Y-m-d'),
        ]);
        // Cesado del mismo mes → NO debe aparecer
        Empleado::create([
            'numero_documento' => '40404040', 'nombres' => 'Cesado', 'apellidos' => 'Fuera',
            'situacion' => 'cesado', 'fecha_nacimiento' => today()->copy()->subYears(40)->format('Y-m-d'),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Cumpleaños de')
            ->assertSee('Ana Torres')
            ->assertSee('Beto Ramos')
            ->assertSee('¡Feliz cumpleaños!')
            ->assertDontSee('Cesado Fuera');
    }

    public function test_trabajador_es_redirigido_a_mi_espacio(): void
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Empleado');
        Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Juan', 'apellidos' => 'Pérez', 'user_id' => $user->id]);

        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('portal.index'));
    }
}
