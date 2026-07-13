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

class MarcacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function trabajador(): Empleado
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Empleado');
        $empleado = Empleado::create([
            'numero_documento' => '10101010', 'nombres' => 'Juan', 'apellidos' => 'Pérez', 'user_id' => $user->id,
        ]);
        $this->actingAs($user);

        return $empleado;
    }

    public function test_marca_ingreso_con_gps_y_luego_salida(): void
    {
        $empleado = $this->trabajador();

        Volt::test('portal.index')
            ->call('marcar', -12.0464, -77.0428, 12.5)
            ->assertHasNoErrors();

        $ingreso = Marcacion::where('empleado_id', $empleado->id)->first();
        $this->assertSame('ingreso', $ingreso->tipo);
        $this->assertEquals(-12.0464, (float) $ingreso->latitud);
        $this->assertFalse($ingreso->es_manual);

        // Segunda marcación → salida (alterna)
        Volt::test('portal.index')->call('marcar', -12.05, -77.05)->assertHasNoErrors();

        $this->assertSame(2, Marcacion::where('empleado_id', $empleado->id)->count());
        $ultima = Marcacion::where('empleado_id', $empleado->id)->orderByDesc('id')->first();
        $this->assertSame('salida', $ultima->tipo);
    }

    public function test_varias_jornadas_el_mismo_dia(): void
    {
        $empleado = $this->trabajador();

        // ingreso, salida, ingreso, salida = 2 jornadas
        foreach ([1, 2, 3, 4] as $i) {
            Volt::test('portal.index')->call('marcar', -12.0, -77.0)->assertHasNoErrors();
        }

        $tipos = Marcacion::where('empleado_id', $empleado->id)->orderBy('id')->pluck('tipo')->all();
        $this->assertSame(['ingreso', 'salida', 'ingreso', 'salida'], $tipos);
    }
}
