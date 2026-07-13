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

class ReporteAsistenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function supervisor(): void
    {
        $this->seed(CatalogoSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('Supervisor');
        $this->actingAs($u);
    }

    private function marcar(int $empleadoId, string $tipo, string $fechaHora): void
    {
        Marcacion::create(['empleado_id' => $empleadoId, 'tipo' => $tipo, 'fecha_hora' => $fechaHora]);
    }

    public function test_calcula_jornadas_y_horas(): void
    {
        $this->supervisor();
        $e = Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Juan', 'apellidos' => 'Pérez']);
        // 2 jornadas el mismo día: 08-12 y 13-17 = 8h
        $this->marcar($e->id, 'ingreso', '2026-07-05 08:00:00');
        $this->marcar($e->id, 'salida', '2026-07-05 12:00:00');
        $this->marcar($e->id, 'ingreso', '2026-07-05 13:00:00');
        $this->marcar($e->id, 'salida', '2026-07-05 17:00:00');

        Volt::test('asistencia.reporte')
            ->set('desde', '2026-07-01')
            ->set('hasta', '2026-07-31')
            ->assertSee('Pérez')
            ->assertSee('8h 0m');
    }

    public function test_jornada_que_cruza_medianoche(): void
    {
        $this->supervisor();
        $e = Empleado::create(['numero_documento' => '20202020', 'nombres' => 'Noc', 'apellidos' => 'Turno']);
        $this->marcar($e->id, 'ingreso', '2026-07-05 22:00:00');
        $this->marcar($e->id, 'salida', '2026-07-06 02:00:00'); // 4h cruzando medianoche

        Volt::test('asistencia.reporte')
            ->set('desde', '2026-07-01')->set('hasta', '2026-07-31')
            ->assertSee('4h 0m');
    }

    public function test_detallado_muestra_trazabilidad_con_tickets(): void
    {
        $this->supervisor();
        $e = Empleado::create(['numero_documento' => '40404040', 'nombres' => 'Luis', 'apellidos' => 'Campo']);
        $this->marcar($e->id, 'ingreso', '2026-07-05 08:00:00');

        $cliente = Cliente::create(['razon_social' => 'C']);
        $suc = Sucursal::create(['cliente_id' => $cliente->id, 'nombre' => 'Local', 'latitud' => -12, 'longitud' => -77, 'radio_metros' => 100]);
        $ticket = Ticket::create(['ticket_atencion' => 'TA-77', 'cliente_id' => $cliente->id, 'sucursal_id' => $suc->id]);
        $tt = TicketTecnico::create(['ticket_id' => $ticket->id, 'empleado_id' => $e->id, 'estado_trabajo' => 'en_ejecucion']);
        TicketAvance::create(['ticket_tecnico_id' => $tt->id, 'estado' => 'en_ejecucion', 'fecha_hora' => '2026-07-05 09:30:00', 'dentro_geocerca' => true, 'latitud' => -12, 'longitud' => -77]);

        $this->marcar($e->id, 'salida', '2026-07-05 17:00:00');

        Volt::test('asistencia.reporte')
            ->set('tipo', 'detallado')
            ->set('empleado_id', $e->id)
            ->set('desde', '2026-07-01')->set('hasta', '2026-07-31')
            ->assertSee('Turno 1')
            ->assertSee('TA-77')
            ->assertSee('En ejecución')
            ->assertSee('dentro de zona');
    }

    public function test_exporta_general_xls(): void
    {
        $this->supervisor();
        $e = Empleado::create(['numero_documento' => '30303030', 'nombres' => 'Ana', 'apellidos' => 'Díaz']);
        $this->marcar($e->id, 'ingreso', '2026-07-05 08:00:00');
        $this->marcar($e->id, 'salida', '2026-07-05 12:00:00');

        Volt::test('asistencia.reporte')
            ->set('tipo', 'general')
            ->set('desde', '2026-07-01')->set('hasta', '2026-07-31')
            ->call('exportar')
            ->assertFileDownloaded('reporte-asistencia-general-2026-07-01_2026-07-31.xls');
    }

    public function test_exporta_detallado_xls_con_trazabilidad(): void
    {
        $this->supervisor();
        $e = Empleado::create(['numero_documento' => '40404040', 'nombres' => 'Luis', 'apellidos' => 'Campo']);
        $this->marcar($e->id, 'ingreso', '2026-07-05 08:00:00');

        $cliente = Cliente::create(['razon_social' => 'C']);
        $suc = Sucursal::create(['cliente_id' => $cliente->id, 'nombre' => 'Local', 'latitud' => -12, 'longitud' => -77, 'radio_metros' => 100]);
        $ticket = Ticket::create(['ticket_atencion' => 'TA-77', 'cliente_id' => $cliente->id, 'sucursal_id' => $suc->id]);
        $tt = TicketTecnico::create(['ticket_id' => $ticket->id, 'empleado_id' => $e->id, 'estado_trabajo' => 'en_ejecucion']);
        TicketAvance::create(['ticket_tecnico_id' => $tt->id, 'estado' => 'en_ejecucion', 'fecha_hora' => '2026-07-05 09:30:00', 'dentro_geocerca' => true, 'latitud' => -12, 'longitud' => -77]);
        $this->marcar($e->id, 'salida', '2026-07-05 17:00:00');

        Volt::test('asistencia.reporte')
            ->set('tipo', 'detallado')
            ->set('desde', '2026-07-01')->set('hasta', '2026-07-31')
            ->call('exportar')
            ->assertFileDownloaded('reporte-asistencia-detallado-2026-07-01_2026-07-31.xls');
    }
}
