<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\RendicionAmpliacion;
use App\Models\RendicionDeposito;
use App\Models\RendicionLiquidacion;
use App\Models\Sucursal;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RendicionEstadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_transiciones_desde_rindiendo(): void
    {
        $d = new RendicionDeposito(['estado' => RendicionDeposito::RINDIENDO]);

        $this->assertTrue($d->puede('liquidar'));
        $this->assertTrue($d->puede('ampliar'));
        $this->assertTrue($d->puede('anular'));
        $this->assertFalse($d->puede('aprobar'));
        $this->assertFalse($d->puede('rechazar'));

        $d->transicionar('liquidar');
        $this->assertSame(RendicionDeposito::POR_REVISAR, $d->estado);
    }

    public function test_flujo_por_revisar_aprobar(): void
    {
        $d = new RendicionDeposito(['estado' => RendicionDeposito::POR_REVISAR]);

        $this->assertTrue($d->puede('aprobar'));
        $this->assertTrue($d->puede('rechazar'));
        $this->assertTrue($d->puede('anular'));
        $this->assertFalse($d->puede('liquidar'));

        $d->transicionar('aprobar');
        $this->assertSame(RendicionDeposito::FINALIZADO, $d->estado);
        $this->assertFalse($d->puede('anular')); // finalizado = solo lectura
    }

    public function test_rechazar_va_a_observado_y_permite_reenviar(): void
    {
        $d = new RendicionDeposito(['estado' => RendicionDeposito::POR_REVISAR]);
        $d->transicionar('rechazar');

        $this->assertSame(RendicionDeposito::OBSERVADO, $d->estado);
        $this->assertTrue($d->puede('liquidar')); // el técnico corrige y reenvía
        $this->assertTrue($d->puede('ampliar'));
        $this->assertTrue($d->puede('anular'));
    }

    public function test_ampliar_no_cambia_el_estado(): void
    {
        $d = new RendicionDeposito(['estado' => RendicionDeposito::RINDIENDO]);
        $d->transicionar('ampliar');
        $this->assertSame(RendicionDeposito::RINDIENDO, $d->estado);
    }

    public function test_transicion_invalida_lanza_excepcion(): void
    {
        $d = new RendicionDeposito(['estado' => RendicionDeposito::FINALIZADO]);
        $this->expectException(\DomainException::class);
        $d->transicionar('aprobar');
    }

    public function test_tipo_de_liquidacion_por_diferencia(): void
    {
        $this->assertSame(RendicionDeposito::LIQ_EXACTO, RendicionLiquidacion::tipoPorDiferencia(0));
        $this->assertSame(RendicionDeposito::LIQ_DEVOLUCION, RendicionLiquidacion::tipoPorDiferencia(50.0));
        $this->assertSame(RendicionDeposito::LIQ_REEMBOLSO, RendicionLiquidacion::tipoPorDiferencia(-25.0));
    }

    public function test_monto_inicial_descuenta_ampliaciones(): void
    {
        $emp = Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Juan', 'apellidos' => 'Pérez']);
        $cliente = Cliente::create(['razon_social' => 'C']);
        $suc = Sucursal::create(['cliente_id' => $cliente->id, 'nombre' => 'Local']);
        $ticket = Ticket::create(['ticket_atencion' => 'TA-1', 'cliente_id' => $cliente->id, 'sucursal_id' => $suc->id]);
        $user = User::factory()->create();

        $dep = RendicionDeposito::create([
            'empleado_id' => $emp->id, 'ticket_id' => $ticket->id, 'supervisor_id' => $user->id,
            'monto' => 600, 'dia' => now()->toDateString(), 'token' => RendicionDeposito::nuevoToken(),
            'estado' => RendicionDeposito::RINDIENDO,
        ]);
        RendicionAmpliacion::create(['deposito_id' => $dep->id, 'monto' => 100, 'fecha' => now()->toDateString()]);

        $this->assertEqualsWithDelta(500.0, $dep->fresh()->monto_inicial, 0.001);
    }

    public function test_permisos_de_rendiciones_existen_y_los_tiene_supervisor(): void
    {
        $this->seed(CatalogoSeeder::class);

        foreach (['ver', 'registrar', 'aprobar', 'ampliar', 'anular'] as $accion) {
            $this->assertTrue(Permission::where('name', "rendiciones.{$accion}")->exists());
        }

        $supervisor = Role::findByName('Supervisor');
        $this->assertTrue($supervisor->hasPermissionTo('rendiciones.registrar'));
        $this->assertTrue($supervisor->hasPermissionTo('rendiciones.aprobar'));
    }
}
