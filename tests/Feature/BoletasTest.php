<?php

namespace Tests\Feature;

use App\Models\BoletaPago;
use App\Models\Empleado;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BoletasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(CatalogoSeeder::class);
    }

    private function rrhh(): User
    {
        $u = User::factory()->create();
        $u->assignRole('RRHH');
        $this->actingAs($u);

        return $u;
    }

    private function trabajador(string $doc = '11223344'): array
    {
        $u = User::factory()->create();
        $u->assignRole('Empleado');
        $emp = Empleado::create(['numero_documento' => $doc, 'nombres' => 'Ana', 'apellidos' => 'Díaz']);
        $emp->forceFill(['user_id' => $u->id])->save();

        return [$u, $emp];
    }

    private function boleta(Empleado $emp, string $periodo = '2026-07-01'): BoletaPago
    {
        Storage::disk('public')->put('boletas/test.pdf', '%PDF-1.4 test');

        return BoletaPago::create([
            'empleado_id' => $emp->id, 'periodo' => $periodo, 'tipo' => 'Mensual',
            'archivo_nombre' => 'Boleta_Mensual_2026-07.pdf', 'archivo_path' => 'boletas/test.pdf',
            'archivo_status' => 'pendiente',
        ]);
    }

    public function test_rrhh_sube_boleta(): void
    {
        Storage::fake('public');
        $this->rrhh();
        [, $emp] = $this->trabajador();

        Volt::test('boletas.tabla')
            ->set('empleado_id', $emp->id)
            ->set('periodo', '2026-07')
            ->set('tipo', 'Mensual')
            ->set('archivo', UploadedFile::fake()->create('boleta.pdf', 90, 'application/pdf'))
            ->call('subir')
            ->assertHasNoErrors();

        $b = BoletaPago::first();
        $this->assertSame($emp->id, $b->empleado_id);
        $this->assertSame('2026-07-01', $b->periodo->toDateString());
        $this->assertSame('pendiente', $b->archivo_status); // sin Graph en tests, queda local
        Storage::disk('public')->assertExists($b->archivo_path);
    }

    public function test_trabajador_ve_y_descarga_su_boleta(): void
    {
        Storage::fake('public');
        [$u, $emp] = $this->trabajador();
        $b = $this->boleta($emp);

        $this->actingAs($u)->get('/mi-espacio')->assertOk()->assertSee('Mis boletas (1)');
        $this->actingAs($u)->get(route('portal.boleta', $b))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_no_puede_ver_boleta_ajena(): void
    {
        Storage::fake('public');
        [, $empAjeno] = $this->trabajador('99887766');
        $b = $this->boleta($empAjeno);

        [$u] = $this->trabajador('11223344');
        $this->actingAs($u)->get(route('portal.boleta', $b))->assertForbidden();
    }

    public function test_trabajador_confirma_recepcion(): void
    {
        Storage::fake('public');
        [$u, $emp] = $this->trabajador();
        $b = $this->boleta($emp);
        $this->assertNull($b->recibida_at);

        $this->actingAs($u);
        Volt::test('portal.index')
            ->call('confirmarRecepcionBoleta', $b->id)
            ->assertHasNoErrors();

        $this->assertNotNull($b->fresh()->recibida_at);
    }

    public function test_empleado_no_accede_a_la_admin_de_boletas(): void
    {
        [$u] = $this->trabajador();
        $this->actingAs($u)->get('/boletas')->assertForbidden();
    }
}
