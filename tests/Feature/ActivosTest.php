<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\CategoriaActivo;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ActivosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usuarioConRol(string $rol): User
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    public function test_el_inventario_carga_para_rrhh(): void
    {
        $user = $this->usuarioConRol('RRHH');
        $cat = CategoriaActivo::first();
        Activo::create(['categoria_id' => $cat->id, 'nombre' => 'Taladro de prueba', 'costo' => 300, 'estado' => 'disponible']);

        $this->actingAs($user)
            ->get('/activos')
            ->assertOk()
            ->assertSee('Inventario de activos')
            ->assertSee('Taladro de prueba');
    }

    public function test_editar_activo_con_descripcion_y_codigo_nulos(): void
    {
        $user = $this->usuarioConRol('RRHH');
        $cat = CategoriaActivo::first();
        // Sin código ni descripción (columnas nullables)
        $activo = Activo::create(['categoria_id' => $cat->id, 'nombre' => 'Escalera', 'costo' => 120, 'estado' => 'disponible']);

        $this->actingAs($user);
        Volt::test('activos.tabla')
            ->call('editar', $activo->id)
            ->assertHasNoErrors()
            ->assertSet('nombre', 'Escalera')
            ->assertSet('descripcion', '')
            ->assertSet('codigo', '');
    }

    public function test_un_empleado_sin_permisos_no_accede(): void
    {
        $user = $this->usuarioConRol('Empleado');

        $this->actingAs($user)->get('/activos')->assertForbidden();
    }

    public function test_se_crea_el_rol_contador(): void
    {
        $this->seed(CatalogoSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'Contador']);
    }
}
