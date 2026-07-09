<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\CategoriaActivo;
use App\Models\Empleado;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AsignacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_asignar_y_devolver_un_activo(): void
    {
        Storage::fake('public');
        $this->seed(CatalogoSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('RRHH');
        $this->actingAs($user);

        $empleado = Empleado::create([
            'numero_documento' => '11112222',
            'nombres' => 'Ana',
            'apellidos' => 'Torres',
        ]);
        $activo = Activo::create([
            'categoria_id' => CategoriaActivo::first()->id,
            'nombre' => 'Taladro',
            'costo' => 300,
            'estado' => 'disponible',
        ]);

        // Asignar
        Volt::test('activos.tabla')
            ->set('asignarId', $activo->id)
            ->set('asignEmpleadoId', $empleado->id)
            ->set('firmaEntrega', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==')
            ->call('asignar')
            ->assertHasNoErrors();

        $this->assertSame('asignado', $activo->fresh()->estado);
        $this->assertDatabaseHas('asignaciones', [
            'activo_id' => $activo->id,
            'empleado_id' => $empleado->id,
            'fecha_devolucion' => null,
        ]);

        // Devolver en buen estado
        Volt::test('activos.tabla')
            ->set('devolverId', $activo->id)
            ->set('devEstado', 'bueno')
            ->call('devolver')
            ->assertHasNoErrors();

        $this->assertSame('disponible', $activo->fresh()->estado);
        $this->assertNotNull(Asignacion::first()->fecha_devolucion);
    }

    public function test_ver_historial_muestra_quienes_lo_tuvieron(): void
    {
        Storage::fake('public');
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');
        $this->actingAs($user);

        $empleado = Empleado::create(['numero_documento' => '33334444', 'nombres' => 'Ana', 'apellidos' => 'Torres']);
        $activo = Activo::create(['categoria_id' => CategoriaActivo::first()->id, 'nombre' => 'Taladro', 'costo' => 300, 'estado' => 'disponible']);

        Volt::test('activos.tabla')
            ->set('asignarId', $activo->id)
            ->set('asignEmpleadoId', $empleado->id)
            ->set('firmaEntrega', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==')
            ->call('asignar');

        Volt::test('activos.tabla')
            ->call('verHistorial', $activo->id)
            ->assertSet('historialId', $activo->id)
            ->assertSee('Torres');
    }

    public function test_asignar_requiere_empleado_y_firma(): void
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');
        $this->actingAs($user);

        $activo = Activo::create([
            'categoria_id' => CategoriaActivo::first()->id,
            'nombre' => 'Celular',
            'costo' => 500,
            'estado' => 'disponible',
        ]);

        Volt::test('activos.tabla')
            ->set('asignarId', $activo->id)
            ->call('asignar')
            ->assertHasErrors(['asignEmpleadoId', 'firmaEntrega']);
    }
}
