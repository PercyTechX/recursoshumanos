<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmpleadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Evita cache de permisos entre tests con BD recreada.
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usuarioConRol(string $rol): User
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    public function test_el_indice_de_empleados_carga_para_rrhh(): void
    {
        $user = $this->usuarioConRol('RRHH');
        $this->seed(DemoSeeder::class);

        $this->actingAs($user)
            ->get('/empleados')
            ->assertOk()
            ->assertSee('Empleados')
            ->assertSee('Pérez Quispe'); // proviene del DemoSeeder
    }

    public function test_un_empleado_sin_permisos_no_accede(): void
    {
        $user = $this->usuarioConRol('Empleado');

        $this->actingAs($user)
            ->get('/empleados')
            ->assertForbidden();
    }

    public function test_la_exportacion_devuelve_un_csv(): void
    {
        $user = $this->usuarioConRol('RRHH');
        $this->seed(DemoSeeder::class);

        $resp = $this->actingAs($user)->get(route('empleados.exportar'));

        $resp->assertOk();
        $this->assertStringContainsString('text/csv', (string) $resp->headers->get('Content-Type'));
    }
}
