<?php

namespace Tests\Feature;

use App\Models\Derechohabiente;
use App\Models\Empleado;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DerechohabientesTest extends TestCase
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

    public function test_agregar_hijo_con_documento_y_cuenta_hijos(): void
    {
        Storage::fake('public');
        $this->admin();
        $empleado = Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Rosa', 'apellidos' => 'Díaz']);

        Volt::test('empleados.expediente', ['empleado' => $empleado])
            ->call('abrirDh')
            ->set('dh_tipo', 'hijo')
            ->set('dh_nombres', 'Luis')
            ->set('dh_apellidos', 'Díaz')
            ->set('dh_numero_documento', '80808080')
            ->set('dh_archivo', UploadedFile::fake()->create('partida.pdf', 40, 'application/pdf'))
            ->call('guardarDh')
            ->assertHasNoErrors();

        $dh = Derechohabiente::where('empleado_id', $empleado->id)->first();
        $this->assertNotNull($dh);
        $this->assertSame('hijo', $dh->tipo);
        $this->assertNotNull($dh->archivo_path);
        Storage::disk('public')->assertExists($dh->archivo_path);
        $this->assertSame(1, $empleado->fresh()->cantidad_hijos);
    }

    public function test_nombres_es_obligatorio(): void
    {
        $this->admin();
        $empleado = Empleado::create(['numero_documento' => '20202020', 'nombres' => 'Ivan', 'apellidos' => 'Soto']);

        Volt::test('empleados.expediente', ['empleado' => $empleado])
            ->call('abrirDh')
            ->set('dh_nombres', '')
            ->call('guardarDh')
            ->assertHasErrors(['dh_nombres']);
    }

    public function test_empleado_guarda_modalidad_afp_y_seguro(): void
    {
        $this->admin();

        Volt::test('empleados.tabla')
            ->call('nuevo')
            ->set('numero_documento', '30303030')
            ->set('nombres', 'Nora')
            ->set('apellidos', 'Paz')
            ->set('modalidad_pago', 'honorarios')
            ->set('afp_nombre', 'Integra')
            ->set('tiene_seguro', '0')
            ->call('guardar')
            ->assertHasNoErrors();

        $e = Empleado::where('numero_documento', '30303030')->first();
        $this->assertSame('honorarios', $e->modalidad_pago);
        $this->assertSame('Integra', $e->afp_nombre);
        $this->assertFalse($e->tiene_seguro);
    }
}
