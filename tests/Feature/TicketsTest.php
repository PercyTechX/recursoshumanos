<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Sede;
use App\Models\Sucursal;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TicketsTest extends TestCase
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

    private function cliente(): Cliente
    {
        $c = Cliente::create(['razon_social' => 'Cliente SAC']);
        Sucursal::create(['cliente_id' => $c->id, 'nombre' => 'Local 1', 'latitud' => -12, 'longitud' => -77, 'radio_metros' => 100]);

        return $c;
    }

    public function test_supervisor_crea_ticket_en_sucursal(): void
    {
        $this->actor('Supervisor');
        $cliente = $this->cliente();
        $suc = $cliente->sucursales->first();

        Volt::test('tickets.tabla')
            ->call('nuevo')
            ->set('ticket_atencion', 'TA-001')
            ->set('cliente_id', $cliente->id)
            ->set('ubicacion_tipo', 'sucursal')
            ->set('sucursal_id', $suc->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $t = Ticket::first();
        $this->assertSame('TA-001', $t->ticket_atencion);
        $this->assertSame('abierto', $t->estado);
        $this->assertSame($suc->id, $t->sucursal_id);
        $this->assertNull($t->sede_id);
        $this->assertSame($suc->id, $t->ubicacion()->id);
    }

    public function test_ticket_atencion_es_unico(): void
    {
        $this->actor('Supervisor');
        $cliente = $this->cliente();
        Ticket::create(['ticket_atencion' => 'TA-001', 'cliente_id' => $cliente->id, 'sucursal_id' => $cliente->sucursales->first()->id]);

        Volt::test('tickets.tabla')
            ->call('nuevo')
            ->set('ticket_atencion', 'TA-001')
            ->set('cliente_id', $cliente->id)
            ->set('ubicacion_tipo', 'sucursal')
            ->set('sucursal_id', $cliente->sucursales->first()->id)
            ->call('guardar')
            ->assertHasErrors(['ticket_atencion']);
    }

    public function test_ticket_en_nuestra_sede(): void
    {
        $this->actor('Supervisor');
        $cliente = $this->cliente();
        $sede = Sede::create(['nombre' => 'Almacén', 'tipo' => 'almacen', 'latitud' => -12, 'longitud' => -77, 'radio_metros' => 100]);

        Volt::test('tickets.tabla')
            ->call('nuevo')
            ->set('ticket_atencion', 'TA-002')
            ->set('cliente_id', $cliente->id)
            ->set('ubicacion_tipo', 'sede')
            ->set('sede_id', $sede->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $t = Ticket::where('ticket_atencion', 'TA-002')->first();
        $this->assertSame($sede->id, $t->sede_id);
        $this->assertNull($t->sucursal_id);
    }

    public function test_cerrar_ticket(): void
    {
        $user = $this->actor('Supervisor');
        $cliente = $this->cliente();
        $t = Ticket::create(['ticket_atencion' => 'TA-003', 'cliente_id' => $cliente->id, 'sucursal_id' => $cliente->sucursales->first()->id]);

        Volt::test('tickets.tabla')->call('cerrar', $t->id)->assertHasNoErrors();

        $t->refresh();
        $this->assertSame('cerrado', $t->estado);
        $this->assertSame($user->id, $t->cerrado_por);
        $this->assertNotNull($t->fecha_cierre);
    }
}
