<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\RendicionDeposito;
use App\Models\RendicionLiquidacion;
use App\Models\Sucursal;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RendicionPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function supervisor(): User
    {
        $this->seed(CatalogoSeeder::class);
        $u = User::factory()->create(['name' => 'Willy Tello']);
        $u->assignRole('Supervisor');
        $this->actingAs($u);

        return $u;
    }

    private function ticket(): Ticket
    {
        $cliente = Cliente::create(['razon_social' => 'Cliente ABC', 'nombre_comercial' => 'ABC']);
        $suc = Sucursal::create(['cliente_id' => $cliente->id, 'nombre' => 'Sede Centro']);

        return Ticket::create(['ticket_atencion' => 'TA-9875654', 'cliente_id' => $cliente->id, 'sucursal_id' => $suc->id]);
    }

    private function empleado(): Empleado
    {
        return Empleado::create(['numero_documento' => '44556677', 'nombres' => 'Clever', 'apellidos' => 'Serrano', 'telefono' => '987654321']);
    }

    private function deposito(string $estado, float $monto = 500): RendicionDeposito
    {
        return RendicionDeposito::create([
            'empleado_id' => $this->empleado()->id, 'ticket_id' => $this->ticket()->id,
            'supervisor_id' => auth()->id(), 'monto' => $monto, 'dia' => now()->toDateString(),
            'token' => RendicionDeposito::nuevoToken(), 'estado' => $estado, 'tecnico_nombre' => 'Serrano, Clever',
        ]);
    }

    public function test_pagina_carga(): void
    {
        $this->supervisor();
        $this->get('/rendiciones')->assertOk()->assertSee('Rendición de Cuentas');
    }

    public function test_supervisor_registra_deposito(): void
    {
        $sup = $this->supervisor();
        $emp = $this->empleado();
        $ticket = $this->ticket();

        Volt::test('rendiciones.tabla')
            ->set('empleado_id', $emp->id)
            ->set('ticket_id', $ticket->id)
            ->set('monto', '500')
            ->set('dia', '2026-07-15')
            ->call('registrar')
            ->assertHasNoErrors();

        $d = RendicionDeposito::first();
        $this->assertSame('Rindiendo', $d->estado);
        $this->assertSame($emp->id, $d->empleado_id);
        $this->assertSame($sup->id, $d->supervisor_id);
        $this->assertNotEmpty($d->token);
        $this->assertStringContainsString('Sede Centro', $d->local_nombre);   // local snapshot del ticket
        $this->assertSame('987654321', $d->tecnico_celular);                  // snapshot
    }

    public function test_registra_deposito_con_voucher(): void
    {
        $this->supervisor();
        $emp = $this->empleado();
        $ticket = $this->ticket();
        Storage::fake('public');

        Volt::test('rendiciones.tabla')
            ->set('empleado_id', $emp->id)
            ->set('ticket_id', $ticket->id)
            ->set('monto', '100')
            ->set('dia', '2026-07-15')
            ->set('voucher', UploadedFile::fake()->create('voucher.pdf', 120, 'application/pdf'))
            ->call('registrar')
            ->assertHasNoErrors();

        $d = RendicionDeposito::first();
        $this->assertNotNull($d->voucher_path);
        $this->assertSame('pendiente', $d->voucher_status);   // Fase D lo subirá a SharePoint
        Storage::disk('public')->assertExists($d->voucher_path);
    }

    public function test_aprobar_finaliza(): void
    {
        $this->supervisor();
        $d = $this->deposito(RendicionDeposito::POR_REVISAR);

        Volt::test('rendiciones.tabla')
            ->call('abrirAccion', 'aprobar', $d->id)
            ->call('confirmarAccion')
            ->assertHasNoErrors();

        $this->assertSame('Finalizado', $d->fresh()->estado);
        $this->assertNotNull($d->fresh()->fecha_aprobado);
    }

    public function test_rechazar_observa_con_motivo(): void
    {
        $this->supervisor();
        $d = $this->deposito(RendicionDeposito::POR_REVISAR);

        Volt::test('rendiciones.tabla')
            ->call('abrirAccion', 'rechazar', $d->id)
            ->set('motivo', 'Falta boleta del taxi')
            ->call('confirmarAccion')
            ->assertHasNoErrors();

        $this->assertSame('Observado', $d->fresh()->estado);
        $this->assertSame('Falta boleta del taxi', $d->fresh()->observaciones);
    }

    public function test_anular_requiere_motivo(): void
    {
        $this->supervisor();
        $d = $this->deposito(RendicionDeposito::RINDIENDO);

        Volt::test('rendiciones.tabla')
            ->call('abrirAccion', 'anular', $d->id)
            ->set('motivo', '')
            ->call('confirmarAccion')
            ->assertHasErrors(['motivo']);

        $this->assertSame('Rindiendo', $d->fresh()->estado); // no cambió
    }

    public function test_aprobar_reembolso_exige_voucher(): void
    {
        $this->supervisor();
        $d = $this->deposito(RendicionDeposito::POR_REVISAR);
        RendicionLiquidacion::create([
            'deposito_id' => $d->id, 'monto_depositado' => 200, 'total_gastado' => 250,
            'diferencia' => -50, 'estado_liquidacion' => 'Reembolso',
        ]);
        Storage::fake('public');

        // Sin voucher: falla y no finaliza
        Volt::test('rendiciones.tabla')
            ->call('abrirAccion', 'aprobar', $d->id)
            ->call('confirmarAccion')
            ->assertHasErrors(['reembolsoVoucher']);
        $this->assertSame('Por Revisar', $d->fresh()->estado);

        // Con voucher: finaliza y guarda el comprobante del reembolso
        Volt::test('rendiciones.tabla')
            ->call('abrirAccion', 'aprobar', $d->id)
            ->set('reembolsoVoucher', UploadedFile::fake()->create('reembolso.jpg', 80, 'image/jpeg'))
            ->call('confirmarAccion')
            ->assertHasNoErrors();

        $this->assertSame('Finalizado', $d->fresh()->estado);
        $this->assertNotNull($d->fresh()->liquidacion->comprobante_path);
    }

    public function test_ampliar_incrementa_el_monto(): void
    {
        $this->supervisor();
        $d = $this->deposito(RendicionDeposito::RINDIENDO, 500);

        Volt::test('rendiciones.tabla')
            ->call('abrirAccion', 'ampliar', $d->id)
            ->set('ampMonto', '100')
            ->set('ampFecha', '2026-07-15')
            ->set('motivo', 'Materiales extra')
            ->call('confirmarAccion')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(600.0, (float) $d->fresh()->monto, 0.001);
        $this->assertSame(1, $d->ampliaciones()->count());
    }
}
