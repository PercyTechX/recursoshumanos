<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Marcacion;
use App\Models\Sucursal;
use App\Models\Ticket;
use App\Models\TicketAvance;
use App\Models\TicketTecnico;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ControlAsistenciaTest extends TestCase
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
        $user = User::factory()->create();
        $user->assignRole('Supervisor');
        $this->actingAs($user);

        return $user;
    }

    private function empleado(): Empleado
    {
        return Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Juan', 'apellidos' => 'Pérez']);
    }

    public function test_supervisor_registra_marcacion_manual(): void
    {
        $sup = $this->supervisor();
        $emp = $this->empleado();

        Volt::test('asistencia.tabla')
            ->call('nuevaMarc')
            ->set('m_empleado_id', $emp->id)
            ->set('m_tipo', 'ingreso')
            ->set('m_fecha_hora', '2026-08-01T08:00')
            ->set('m_motivo', 'Sin señal en obra')
            ->call('guardarMarc')
            ->assertHasNoErrors();

        $m = Marcacion::first();
        $this->assertTrue($m->es_manual);
        $this->assertSame($sup->id, $m->registrado_por);
        $this->assertSame('Sin señal en obra', $m->motivo);
        $this->assertSame('ingreso', $m->tipo);
    }

    public function test_motivo_obligatorio_en_manual(): void
    {
        $this->supervisor();
        $emp = $this->empleado();

        Volt::test('asistencia.tabla')
            ->call('nuevaMarc')
            ->set('m_empleado_id', $emp->id)
            ->set('m_fecha_hora', '2026-08-01T08:00')
            ->set('m_motivo', '')
            ->call('guardarMarc')
            ->assertHasErrors(['m_motivo']);
    }

    public function test_corregir_hora_de_una_marcacion(): void
    {
        $this->supervisor();
        $emp = $this->empleado();
        $m = Marcacion::create(['empleado_id' => $emp->id, 'tipo' => 'salida', 'fecha_hora' => '2026-08-01 17:00', 'es_manual' => true, 'motivo' => 'x']);

        Volt::test('asistencia.tabla')
            ->call('editarMarc', $m->id)
            ->set('m_fecha_hora', '2026-08-01T18:30')
            ->call('guardarMarc')
            ->assertHasNoErrors();

        $this->assertSame('2026-08-01 18:30', $m->fresh()->fecha_hora->format('Y-m-d H:i'));
    }

    public function test_marcaciones_filtran_por_defecto_al_mes_actual(): void
    {
        $this->supervisor();
        $emp = $this->empleado();
        $reciente = Marcacion::create(['empleado_id' => $emp->id, 'tipo' => 'ingreso', 'fecha_hora' => now()->setTime(8, 0)]);
        $antigua = Marcacion::create(['empleado_id' => $emp->id, 'tipo' => 'ingreso', 'fecha_hora' => now()->subMonths(3)->setTime(8, 0)]);

        Volt::test('asistencia.tabla')
            ->assertSee($reciente->fecha_hora->format('d/m/Y'))
            ->assertDontSee($antigua->fecha_hora->format('d/m/Y'));
    }

    public function test_liberar_tecnico_de_un_ticket(): void
    {
        $sup = $this->supervisor();
        $emp = $this->empleado();
        $cliente = Cliente::create(['razon_social' => 'C']);
        $suc = Sucursal::create(['cliente_id' => $cliente->id, 'nombre' => 'L']);
        $ticket = Ticket::create(['ticket_atencion' => 'TA-1', 'cliente_id' => $cliente->id, 'sucursal_id' => $suc->id]);
        $tt = TicketTecnico::create(['ticket_id' => $ticket->id, 'empleado_id' => $emp->id, 'estado_trabajo' => 'iniciado']);

        Volt::test('asistencia.tabla')
            ->call('abrirLiberar', $tt->id)
            ->set('liberarMotivo', 'Desviado a emergencia')
            ->call('liberar')
            ->assertHasNoErrors();

        $tt->refresh();
        $this->assertSame('abortado', $tt->estado_trabajo);
        $this->assertSame($sup->id, $tt->liberado_por);
        $this->assertTrue(TicketAvance::where('ticket_tecnico_id', $tt->id)->where('es_manual', true)->exists());
    }

    public function test_avance_manual_de_ticket(): void
    {
        $sup = $this->supervisor();
        $emp = $this->empleado();
        $cliente = Cliente::create(['razon_social' => 'C']);
        $suc = Sucursal::create(['cliente_id' => $cliente->id, 'nombre' => 'L']);
        $ticket = Ticket::create(['ticket_atencion' => 'TA-1', 'cliente_id' => $cliente->id, 'sucursal_id' => $suc->id, 'estado' => 'abierto']);

        Volt::test('asistencia.tabla')
            ->call('nuevoAvance')
            ->set('av_empleado_id', $emp->id)
            ->set('av_ticket_id', $ticket->id)
            ->set('av_estado', 'terminado')
            ->set('av_motivo', 'Reportó por radio')
            ->call('guardarAvance')
            ->assertHasNoErrors();

        $tt = TicketTecnico::first();
        $this->assertSame('terminado', $tt->estado_trabajo);
        $this->assertTrue($tt->avances()->where('es_manual', true)->exists());
    }
}
