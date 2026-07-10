<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Ticket;
use App\Models\TicketTecnico;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TicketOperacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private Empleado $empleado;

    private function escenario(bool $conIngreso = true): Ticket
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Empleado');
        $this->empleado = Empleado::create([
            'numero_documento' => '10101010', 'nombres' => 'Juan', 'apellidos' => 'Pérez', 'user_id' => $user->id,
        ]);
        $this->actingAs($user);

        if ($conIngreso) {
            Marcacion::create(['empleado_id' => $this->empleado->id, 'tipo' => 'ingreso', 'fecha_hora' => now(), 'latitud' => -12, 'longitud' => -77]);
        }

        $cliente = Cliente::create(['razon_social' => 'C SAC']);
        $suc = Sucursal::create(['cliente_id' => $cliente->id, 'nombre' => 'Local', 'latitud' => -12.0, 'longitud' => -77.0, 'radio_metros' => 200]);

        return Ticket::create(['ticket_atencion' => 'TA-1', 'cliente_id' => $cliente->id, 'sucursal_id' => $suc->id, 'estado' => 'abierto']);
    }

    public function test_tomar_avanzar_y_terminar(): void
    {
        $ticket = $this->escenario();

        Volt::test('portal.index')->call('tomarTicket', $ticket->id, -13.0, -78.0)->assertHasNoErrors(); // iniciado: cualquier lugar
        $tt = TicketTecnico::first();
        $this->assertSame('iniciado', $tt->estado_trabajo);

        // En ejecución dentro de la geocerca
        Volt::test('portal.index')->call('avanzar', $tt->id, -12.0, -77.0);
        $this->assertSame('en_ejecucion', $tt->fresh()->estado_trabajo);

        // Terminar dentro de la geocerca → libera
        Volt::test('portal.index')->call('avanzar', $tt->id, -12.0, -77.0);
        $this->assertSame('terminado', $tt->fresh()->estado_trabajo);
        $this->assertSame(3, $tt->avances()->count());
    }

    public function test_en_ejecucion_bloqueada_fuera_de_geocerca(): void
    {
        $ticket = $this->escenario();
        Volt::test('portal.index')->call('tomarTicket', $ticket->id, -12.0, -77.0);
        $tt = TicketTecnico::first();

        // Fuera de la geocerca (lejos) → no avanza
        Volt::test('portal.index')->call('avanzar', $tt->id, -13.5, -78.5);
        $this->assertSame('iniciado', $tt->fresh()->estado_trabajo);
    }

    public function test_no_se_puede_sin_ingreso(): void
    {
        $ticket = $this->escenario(conIngreso: false);
        Volt::test('portal.index')->call('tomarTicket', $ticket->id, -12.0, -77.0);
        $this->assertSame(0, TicketTecnico::count());
    }

    public function test_un_solo_ticket_activo(): void
    {
        $ticket = $this->escenario();
        $otro = Ticket::create(['ticket_atencion' => 'TA-2', 'cliente_id' => $ticket->cliente_id, 'sucursal_id' => $ticket->sucursal_id, 'estado' => 'abierto']);

        Volt::test('portal.index')->call('tomarTicket', $ticket->id, -12.0, -77.0);
        Volt::test('portal.index')->call('tomarTicket', $otro->id, -12.0, -77.0);

        $this->assertSame(1, TicketTecnico::count());
    }

    public function test_abortar_libera_al_tecnico(): void
    {
        $ticket = $this->escenario();
        Volt::test('portal.index')->call('tomarTicket', $ticket->id, -12.0, -77.0);

        Volt::test('portal.index')->call('abortar', -12.0, -77.0);

        $this->assertSame('abortado', TicketTecnico::first()->estado_trabajo);
        $this->assertSame(0, TicketTecnico::whereIn('estado_trabajo', ['iniciado', 'en_ejecucion'])->count());
    }
}
