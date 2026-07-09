<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CeseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function admin(): User
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');
        $this->actingAs($user);

        return $user;
    }

    public function test_cesar_requiere_fecha_de_cese(): void
    {
        $this->admin();
        $empleado = Empleado::create(['numero_documento' => '60607070', 'nombres' => 'Nora', 'apellidos' => 'Lima']);

        Volt::test('empleados.tabla')
            ->call('editar', $empleado->id)
            ->set('situacion', 'cesado')
            ->call('guardar')
            ->assertHasErrors(['fecha_cese']);
    }

    public function test_cesar_con_fecha_guarda_correctamente(): void
    {
        $this->admin();
        $empleado = Empleado::create(['numero_documento' => '80809090', 'nombres' => 'Raúl', 'apellidos' => 'Campos']);

        Volt::test('empleados.tabla')
            ->call('editar', $empleado->id)
            ->set('situacion', 'cesado')
            ->set('fecha_cese', '2026-07-07')
            ->call('guardar')
            ->assertHasNoErrors();

        $empleado->refresh();
        $this->assertSame('cesado', $empleado->situacion);
        $this->assertSame('2026-07-07', $empleado->fecha_cese->format('Y-m-d'));
    }
}
