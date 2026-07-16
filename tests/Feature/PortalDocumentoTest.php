<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Empleado;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Bug fix: el trabajador ve SUS documentos desde el portal (SharePoint o local). */
class PortalDocumentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(CatalogoSeeder::class);
    }

    private function trabajador(string $doc): array
    {
        $u = User::factory()->create();
        $u->assignRole('Empleado');
        $emp = Empleado::create(['numero_documento' => $doc, 'nombres' => 'Ana', 'apellidos' => 'Díaz']);
        $emp->forceFill(['user_id' => $u->id])->save();

        return [$u, $emp];
    }

    private function documento(Empleado $emp): Documento
    {
        Storage::disk('public')->put('documentos/test.pdf', '%PDF-1.4 test');

        return Documento::create([
            'empleado_id' => $emp->id,
            'tipo_documento_id' => TipoDocumento::first()->id,
            'archivo_path' => 'documentos/test.pdf',
            'archivo_nombre' => 'sctr.pdf',
        ]);
    }

    public function test_trabajador_ve_su_propio_documento(): void
    {
        Storage::fake('public');
        [$u, $emp] = $this->trabajador('11223344');
        $d = $this->documento($emp);

        $this->actingAs($u)->get(route('portal.documento', $d))->assertOk();
    }

    public function test_no_puede_ver_documento_de_otro(): void
    {
        Storage::fake('public');
        [, $empAjeno] = $this->trabajador('99887766');
        $d = $this->documento($empAjeno);

        [$u] = $this->trabajador('11223344');
        $this->actingAs($u)->get(route('portal.documento', $d))->assertForbidden();
    }
}
