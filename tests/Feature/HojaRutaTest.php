<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\CategoriaActivo;
use App\Models\Descuento;
use App\Models\Empleado;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HojaRutaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_hoja_de_ruta_genera_descuento_por_activo_no_devuelto(): void
    {
        Storage::fake('public');
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');
        $this->actingAs($user);

        $empleado = Empleado::create(['numero_documento' => '10102020', 'nombres' => 'Beto', 'apellidos' => 'Salas']);
        $activo = Activo::create(['categoria_id' => CategoriaActivo::first()->id, 'nombre' => 'Taladro', 'costo' => 300, 'estado' => 'asignado']);
        $asig = Asignacion::create(['activo_id' => $activo->id, 'empleado_id' => $empleado->id, 'fecha_entrega' => now()->toDateString()]);

        Volt::test('hojas-ruta.crear', ['empleado' => $empleado])
            ->set('items.0.devuelto', false)
            ->set('items.0.monto', 250)
            ->set('firma', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==')
            ->call('generar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('descuentos', [
            'empleado_id' => $empleado->id,
            'activo_id' => $activo->id,
            'estado' => 'pendiente',
        ]);
        $this->assertSame('perdido', $activo->fresh()->estado);
        $this->assertNotNull($asig->fresh()->fecha_devolucion);
    }

    public function test_hoja_de_ruta_devuelto_no_genera_descuento(): void
    {
        Storage::fake('public');
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');
        $this->actingAs($user);

        $empleado = Empleado::create(['numero_documento' => '30304040', 'nombres' => 'Lia', 'apellidos' => 'Prado']);
        $activo = Activo::create(['categoria_id' => CategoriaActivo::first()->id, 'nombre' => 'Laptop', 'costo' => 2000, 'estado' => 'asignado']);
        Asignacion::create(['activo_id' => $activo->id, 'empleado_id' => $empleado->id, 'fecha_entrega' => now()->toDateString()]);

        Volt::test('hojas-ruta.crear', ['empleado' => $empleado])
            ->set('firma', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==')
            ->call('generar')
            ->assertHasNoErrors();

        $this->assertSame(0, Descuento::count());
        $this->assertSame('disponible', $activo->fresh()->estado);
    }

    public function test_el_contador_ve_los_descuentos(): void
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Contador');
        $this->actingAs($user);

        $empleado = Empleado::create(['numero_documento' => '50506060', 'nombres' => 'Ivo', 'apellidos' => 'Nuñez']);
        Descuento::create(['empleado_id' => $empleado->id, 'monto' => 150, 'motivo' => 'Activo no devuelto: X', 'estado' => 'pendiente']);

        $this->get('/descuentos')
            ->assertOk()
            ->assertSee('Nuñez')
            ->assertSee('150');
    }
}
