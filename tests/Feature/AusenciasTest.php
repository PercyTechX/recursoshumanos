<?php

namespace Tests\Feature;

use App\Models\Ausencia;
use App\Models\Empleado;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AusenciasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function admin(): void
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');
        $this->actingAs($user);
    }

    public function test_registrar_descanso_medico_con_archivo(): void
    {
        Storage::fake('public');
        $this->admin();
        $empleado = Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Ana', 'apellidos' => 'Díaz']);

        Volt::test('ausencias.tabla')
            ->call('nuevo')
            ->set('empleado_id', $empleado->id)
            ->set('tipo', 'descanso_medico')
            ->set('fecha_inicio', '2026-08-01')
            ->set('fecha_fin', '2026-08-05')
            ->set('documento_ref', 'CITT 123456')
            ->set('archivo', UploadedFile::fake()->create('citt.pdf', 40, 'application/pdf'))
            ->call('guardar')
            ->assertHasNoErrors();

        $a = Ausencia::first();
        $this->assertSame('descanso_medico', $a->tipo);
        $this->assertSame(5, $a->dias); // inclusivo
        $this->assertTrue($a->con_goce);
        $this->assertNotNull($a->archivo_path);
        Storage::disk('public')->assertExists($a->archivo_path);
    }

    public function test_licencia_sin_goce_ajusta_el_flag(): void
    {
        $this->admin();
        $empleado = Empleado::create(['numero_documento' => '20202020', 'nombres' => 'Beto', 'apellidos' => 'Ruiz']);

        Volt::test('ausencias.tabla')
            ->call('nuevo')
            ->set('tipo', 'licencia_sin_goce') // updatedTipo pone con_goce=false
            ->set('empleado_id', $empleado->id)
            ->set('fecha_inicio', '2026-09-01')
            ->set('fecha_fin', '2026-09-03')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertFalse(Ausencia::first()->con_goce);
    }

    public function test_fecha_fin_no_puede_ser_anterior(): void
    {
        $this->admin();
        $empleado = Empleado::create(['numero_documento' => '30303030', 'nombres' => 'Sol', 'apellidos' => 'Vega']);

        Volt::test('ausencias.tabla')
            ->call('nuevo')
            ->set('empleado_id', $empleado->id)
            ->set('fecha_inicio', '2026-09-10')
            ->set('fecha_fin', '2026-09-01')
            ->call('guardar')
            ->assertHasErrors(['fecha_fin']);
    }
}
