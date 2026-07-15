<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\RendicionDeposito;
use App\Models\RendicionGasto;
use App\Models\Sucursal;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RendicionRendirTest extends TestCase
{
    use RefreshDatabase;

    private function deposito(string $estado, float $monto): RendicionDeposito
    {
        $cliente = Cliente::create(['razon_social' => 'Cliente ABC']);
        $suc = Sucursal::create(['cliente_id' => $cliente->id, 'nombre' => 'Sede Centro']);
        $ticket = Ticket::create(['ticket_atencion' => 'TA-9875654', 'cliente_id' => $cliente->id, 'sucursal_id' => $suc->id]);
        $emp = Empleado::create(['numero_documento' => '44556677', 'nombres' => 'Clever', 'apellidos' => 'Serrano']);

        return RendicionDeposito::create([
            'empleado_id' => $emp->id, 'ticket_id' => $ticket->id, 'monto' => $monto,
            'dia' => now()->toDateString(), 'token' => RendicionDeposito::nuevoToken(),
            'estado' => $estado, 'tecnico_nombre' => 'Serrano, Clever', 'local_nombre' => 'Cliente ABC · Sede Centro (sucursal)',
        ]);
    }

    private function gasto(RendicionDeposito $dep, float $monto): void
    {
        RendicionGasto::create([
            'deposito_id' => $dep->id, 'tipo_comprobante' => 'Boleta', 'nro_comprobante' => 'B-1',
            'monto_gasto' => $monto, 'fecha_comprobante' => now()->toDateString(),
        ]);
    }

    public function test_pagina_publica_carga_por_token(): void
    {
        $dep = $this->deposito(RendicionDeposito::RINDIENDO, 200);
        $this->get('/rendir/'.$dep->token)->assertOk()->assertSee('Serrano, Clever');
    }

    public function test_token_invalido_da_404(): void
    {
        $this->get('/rendir/enlace-que-no-existe')->assertNotFound();
    }

    public function test_agregar_comprobante(): void
    {
        Storage::fake('public');
        $dep = $this->deposito(RendicionDeposito::RINDIENDO, 300);

        Volt::test('rendiciones.rendir', ['deposito' => $dep])
            ->set('c_tipo', 'Boleta')
            ->set('c_monto', '50')
            ->set('c_fecha', '2026-07-15')
            ->set('c_archivo', UploadedFile::fake()->create('boleta.jpg', 80, 'image/jpeg'))
            ->call('agregarComprobante')
            ->assertHasNoErrors();

        $this->assertSame(1, $dep->gastos()->count());
    }

    public function test_liquidar_exacto(): void
    {
        $dep = $this->deposito(RendicionDeposito::RINDIENDO, 200);
        $this->gasto($dep, 200);

        Volt::test('rendiciones.rendir', ['deposito' => $dep])->call('liquidar')->assertHasNoErrors();

        $dep->refresh();
        $this->assertSame('Por Revisar', $dep->estado);
        $this->assertNotNull($dep->fecha_rendido);
        $this->assertSame('Exacto', $dep->liquidacion->estado_liquidacion);
    }

    public function test_liquidar_devolucion_exige_voucher(): void
    {
        Storage::fake('public');
        $dep = $this->deposito(RendicionDeposito::RINDIENDO, 200);
        $this->gasto($dep, 150); // sobra 50 → devolución

        // Sin voucher: falla
        Volt::test('rendiciones.rendir', ['deposito' => $dep])
            ->call('liquidar')
            ->assertHasErrors(['voucherDevolucion']);
        $this->assertSame('Rindiendo', $dep->fresh()->estado);

        // Con voucher: ok
        Volt::test('rendiciones.rendir', ['deposito' => $dep])
            ->set('voucherDevolucion', UploadedFile::fake()->create('vuelto.jpg', 80, 'image/jpeg'))
            ->call('liquidar')
            ->assertHasNoErrors();

        $dep->refresh();
        $this->assertSame('Por Revisar', $dep->estado);
        $this->assertSame('Devolucion', $dep->liquidacion->estado_liquidacion);
        $this->assertNotNull($dep->liquidacion->comprobante_path);
    }

    public function test_liquidar_reembolso(): void
    {
        $dep = $this->deposito(RendicionDeposito::RINDIENDO, 200);
        $this->gasto($dep, 250); // gastó de más → reembolso

        Volt::test('rendiciones.rendir', ['deposito' => $dep])->call('liquidar')->assertHasNoErrors();

        $dep->refresh();
        $this->assertSame('Por Revisar', $dep->estado);
        $this->assertSame('Reembolso', $dep->liquidacion->estado_liquidacion);
    }

    public function test_deposito_no_editable_bloquea_comprobantes(): void
    {
        Storage::fake('public');
        $dep = $this->deposito(RendicionDeposito::FINALIZADO, 200);

        Volt::test('rendiciones.rendir', ['deposito' => $dep])
            ->set('c_tipo', 'Boleta')
            ->set('c_monto', '50')
            ->set('c_fecha', '2026-07-15')
            ->set('c_archivo', UploadedFile::fake()->create('boleta.jpg', 80, 'image/jpeg'))
            ->call('agregarComprobante')
            ->assertForbidden();

        $this->assertSame(0, $dep->gastos()->count());
    }
}
