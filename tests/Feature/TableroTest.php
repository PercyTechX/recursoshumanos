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
