<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\TipoEpp;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function admin(): User
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');
        $this->actingAs($user);

        return $user;
    }

    public function test_el_expediente_carga_con_sus_pestanas(): void
    {
        $this->admin();
        $empleado = Empleado::create(['numero_documento' => '55556666', 'nombres' => 'Carla', 'apellidos' => 'Mendoza']);

        $this->get('/empleados/'.$empleado->id)
            ->assertOk()
            ->assertSee('Mendoza')
            ->assertSee('Documentos')
            ->assertSee('Activos')
            ->assertSee('EPP');
    }

    public function test_registrar_entrega_de_epp(): void
    {
        Storage::fake('public');
        $this->admin();
        $empleado = Empleado::create(['numero_documento' => '77778888', 'nombres' => 'Diego', 'apellidos' => 'Rojas']);
        $tipo = TipoEpp::first();

        Volt::test('empleados.expediente', ['empleado' => $empleado])
            ->set('tipo_epp_id', $tipo->id)
            ->set('cantidad', 2)
            ->set('talla', 'M')
            ->set('firmaEpp', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==')
            ->call('entregarEpp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entregas_epp', [
            'empleado_id' => $empleado->id,
            'tipo_epp_id' => $tipo->id,
            'cantidad' => 2,
            'talla' => 'M',
        ]);
    }

    public function test_entregar_epp_requiere_tipo_y_firma(): void
    {
        $this->admin();
        $empleado = Empleado::create(['numero_documento' => '99990000', 'nombres' => 'Sol', 'apellidos' => 'Vega']);

        Volt::test('empleados.expediente', ['empleado' => $empleado])
            ->call('entregarEpp')
            ->assertHasErrors(['tipo_epp_id', 'firmaEpp']);
    }
}
