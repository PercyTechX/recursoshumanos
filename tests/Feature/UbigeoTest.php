<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Ubigeo;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\UbigeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UbigeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_carga_ubigeos_y_jerarquia(): void
    {
        $this->seed(UbigeoSeeder::class);

        $this->assertGreaterThan(1000, Ubigeo::count());
        $this->assertTrue(Ubigeo::departamentos()->contains('LIMA'));
        $this->assertTrue(Ubigeo::provincias('LIMA')->contains('LIMA'));
        $this->assertTrue(Ubigeo::distritos('LIMA', 'LIMA')->isNotEmpty());
        $this->assertTrue(Ubigeo::provincias(null)->isEmpty());
    }

    public function test_al_cambiar_departamento_se_limpian_provincia_y_distrito(): void
    {
        $this->seed(CatalogoSeeder::class);
        $this->seed(UbigeoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');
        $this->actingAs($user);
        $cliente = Cliente::create(['razon_social' => 'X SAC']);

        Volt::test('sucursales.tabla', ['cliente' => $cliente])
            ->set('departamento', 'LIMA')
            ->set('provincia', 'LIMA')
            ->set('distrito', 'MIRAFLORES')
            ->set('departamento', 'AMAZONAS') // dispara updatedDepartamento
            ->assertSet('provincia', '')
            ->assertSet('distrito', '');
    }
}
