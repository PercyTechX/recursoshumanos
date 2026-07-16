<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Empleado;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** DNI y CV del trabajador: al registrar y desde el expediente (una sola fuente: Documentos). */
class DocumentosInicialesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(CatalogoSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('RRHH');
        $this->actingAs($u);
    }

    public function test_el_catalogo_incluye_dni_con_vigencia(): void
    {
        $dni = TipoDocumento::where('nombre', 'DNI')->first();
        $this->assertNotNull($dni);
        $this->assertTrue((bool) $dni->requiere_vigencia); // el DNI vence → entra al semáforo
    }

    public function test_registrar_empleado_con_dni_y_cv_crea_sus_documentos(): void
    {
        Storage::fake('public');

        Volt::test('empleados.tabla')
            ->call('nuevo')
            ->set('numero_documento', '76543210')
            ->set('nombres', 'María')
            ->set('apellidos', 'Quispe')
            ->set('dni_archivo', UploadedFile::fake()->create('dni.jpg', 90, 'image/jpeg'))
            ->set('dni_vencimiento', '2030-05-10')
            ->set('cv_archivo', UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'))
            ->call('guardar')
            ->assertHasNoErrors();

        $emp = Empleado::where('numero_documento', '76543210')->first();
        $this->assertNotNull($emp);

        $docs = Documento::where('empleado_id', $emp->id)->with('tipoDocumento')->get();
        $this->assertCount(2, $docs);

        $dni = $docs->firstWhere('tipoDocumento.nombre', 'DNI');
        $this->assertNotNull($dni);
        $this->assertSame('2030-05-10', $dni->fecha_vencimiento->toDateString());
        $this->assertSame('pendiente', $dni->upload_status); // sin Graph en tests: local
        Storage::disk('public')->assertExists($dni->archivo_path);

        $cv = $docs->firstWhere('tipoDocumento.nombre', 'Hoja de Vida (CV)');
        $this->assertNotNull($cv);
        Storage::disk('public')->assertExists($cv->archivo_path);
    }

    public function test_registrar_sin_adjuntos_no_crea_documentos(): void
    {
        Volt::test('empleados.tabla')
            ->call('nuevo')
            ->set('numero_documento', '70123456')
            ->set('nombres', 'Luis')
            ->set('apellidos', 'Campos')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(0, Documento::count());
    }

    public function test_sube_documento_desde_el_expediente(): void
    {
        Storage::fake('public');
        $emp = Empleado::create(['numero_documento' => '45678901', 'nombres' => 'Rosa', 'apellidos' => 'Torres']);
        $tipoDni = TipoDocumento::where('nombre', 'DNI')->first();

        Volt::test('empleados.expediente', ['empleado' => $emp])
            ->call('abrirSubirDocumento')
            ->set('doc_tipo_id', $tipoDni->id)
            ->set('doc_fecha_vencimiento', '2031-01-15')
            ->set('doc_archivo', UploadedFile::fake()->create('dni.pdf', 80, 'application/pdf'))
            ->call('subirDocumento')
            ->assertHasNoErrors();

        $doc = Documento::where('empleado_id', $emp->id)->first();
        $this->assertNotNull($doc);
        $this->assertSame($tipoDni->id, $doc->tipo_documento_id);
        $this->assertSame('2031-01-15', $doc->fecha_vencimiento->toDateString());
        Storage::disk('public')->assertExists($doc->archivo_path);
    }
}
