<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\Marcacion;
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

    public function test_exporta_csv(): void
    {
        $this->supervisor();
        $e = Empleado::create(['numero_documento' => '30303030', 'nombres' => 'Ana', 'apellidos' => 'Díaz']);
        $this->marcar($e->id, 'ingreso', '2026-07-05 08:00:00');
        $this->marcar($e->id, 'salida', '2026-07-05 12:00:00');

        Volt::test('asistencia.reporte')
            ->set('desde', '2026-07-01')->set('hasta', '2026-07-31')
            ->call('exportar')
            ->assertFileDownloaded();
    }
}
