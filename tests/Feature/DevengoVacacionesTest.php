<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\MovimientoVacaciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevengoVacacionesTest extends TestCase
{
    use RefreshDatabase;

    private function empleadoConApertura(int $dias, ?string $fechaCorte): Empleado
    {
        $e = Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Luis', 'apellidos' => 'Ramírez']);
        MovimientoVacaciones::create([
            'empleado_id' => $e->id,
            'fecha' => now()->toDateString(),
            'fecha_corte' => $fechaCorte,
            'tipo' => 'apertura',
            'dias' => $dias,
            'observacion' => 'apertura',
        ]);

        return $e;
    }

    public function test_apertura_con_fecha_de_corte_devenga_2_5_por_mes(): void
    {
        // Corte hace 180 días → 180 * 2.5 / 30 = 15 devengados
        $e = $this->empleadoConApertura(15, now()->subDays(180)->toDateString());

        $this->assertEqualsWithDelta(15.0, $e->devengadoVacaciones(), 0.1);
        $this->assertEqualsWithDelta(30.0, $e->saldo_vacaciones, 0.1); // 15 apertura + 15 devengado
    }

    public function test_apertura_sin_fecha_de_corte_no_devenga(): void
    {
        $e = $this->empleadoConApertura(15, null);

        $this->assertSame(0.0, $e->devengadoVacaciones());
        $this->assertEqualsWithDelta(15.0, $e->saldo_vacaciones, 0.01); // saldo fijo
    }

    public function test_corte_futuro_no_devenga(): void
    {
        $e = $this->empleadoConApertura(10, now()->addDays(15)->toDateString());

        $this->assertSame(0.0, $e->devengadoVacaciones());
        $this->assertEqualsWithDelta(10.0, $e->saldo_vacaciones, 0.01);
    }

    public function test_prorrateo_por_dias(): void
    {
        // 45 días → 45 * 2.5 / 30 = 3.75
        $e = $this->empleadoConApertura(0, now()->subDays(45)->toDateString());
        $this->assertEqualsWithDelta(3.75, $e->devengadoVacaciones(), 0.05);
    }
}
