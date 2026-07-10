<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actor(string $rol): User
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($rol);
        $this->actingAs($user);

        return $user;
    }

    public function test_crea_cliente_y_sucursal_con_geocerca(): void
    {
        $this->actor('RRHH');

        Volt::test('clientes.tabla')
            ->call('nuevo')
            ->set('razon_social', 'Cliente SAC')
            ->set('nombre_comercial', 'Cliente')
            ->set('ruc', '20123456789')
            ->call('guardar')
            ->assertHasNoErrors();

        $cliente = Cliente::first();
        $this->assertNotNull($cliente);

        Volt::test('sucursales.tabla', ['cliente' => $cliente])
            ->call('nuevo')
            ->set('nombre', 'Local Centro')
            ->set('latitud', '-12.0464000')
            ->set('longitud', '-77.0428000')
            ->set('radio_metros', 150)
            ->set('distrito', 'Lima')
            ->call('guardar')
            ->assertHasNoErrors();

        $suc = Sucursal::first();
        $this->assertSame($cliente->id, $suc->cliente_id);
        $this->assertSame(150, $suc->radio_metros);
    }

    public function test_geocerca_valida_dentro_y_fuera(): void
    {
        $suc = new Sucursal(['latitud' => -12.0, 'longitud' => -77.0, 'radio_metros' => 200]);

        // Mismo punto → dentro
        $this->assertTrue($suc->contiene(-12.0, -77.0));
        // ~1.5 km → fuera
        $this->assertFalse($suc->contiene(-12.01, -77.01));
    }

    public function test_sin_permiso_no_puede_crear(): void
    {
        $this->actor('Contador'); // no tiene clientes.*

        try {
            Volt::test('clientes.tabla')
                ->call('nuevo')
                ->set('razon_social', 'X')
                ->call('guardar');
        } catch (\Throwable $e) {
            // abort(403) — esperado
        }

        $this->assertSame(0, Cliente::count());
    }
}
