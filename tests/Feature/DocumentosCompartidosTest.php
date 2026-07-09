<?php

namespace Tests\Feature;

use App\Models\DocumentoCompartido;
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

class DocumentosCompartidosTest extends TestCase
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

    public function test_crear_sctr_colectivo_para_varias_personas(): void
    {
        Storage::fake('public');
        $this->admin();

        $salud = TipoDocumento::where('nombre', 'SCTR Salud')->first();
        $pension = TipoDocumento::where('nombre', 'SCTR Pensión')->first();
        $e1 = Empleado::create(['numero_documento' => '46210890', 'nombres' => 'Daniel', 'apellidos' => 'Bazán']);
        $e2 = Empleado::create(['numero_documento' => '71034312', 'nombres' => 'Ana', 'apellidos' => 'Bernal']);

        Volt::test('documentos-compartidos.tabla')
            ->call('nuevo')
            ->set('tiposSel', [(string) $salud->id, (string) $pension->id])
            ->set('aseguradora', [$salud->id => 'Sanitas', $pension->id => 'Crecer'])
            ->set('numero', [$salud->id => '670949', $pension->id => '9000182972'])
            ->set('fecha_emision', '2026-06-20')
            ->set('fecha_vencimiento', '2026-07-19')
            ->set('empleadosSel', [(string) $e1->id, (string) $e2->id])
            ->set('archivo', UploadedFile::fake()->create('sctr.pdf', 60, 'application/pdf'))
            ->call('guardar')
            ->assertHasNoErrors();

        $doc = DocumentoCompartido::first();
        $this->assertNotNull($doc);
        $this->assertCount(2, $doc->coberturas);
        $this->assertSame(2, $doc->empleados()->count());
        $this->assertNotNull($doc->archivo_path);
        Storage::disk('public')->assertExists($doc->archivo_path);
        $this->assertSame('9000182972', $doc->coberturas->firstWhere('tipo_documento_id', $pension->id)->numero_poliza);

        // Aparece como derechohabiente... no: aparece en el expediente del empleado
        $this->assertTrue($e1->documentosCompartidos()->exists());
    }

    public function test_requiere_cobertura_personas_y_archivo(): void
    {
        $this->admin();

        Volt::test('documentos-compartidos.tabla')
            ->call('nuevo')
            ->call('guardar')
            ->assertHasErrors(['tiposSel', 'empleadosSel', 'archivo']);
    }

    public function test_renovar_clona_coberturas_y_grupo_como_nuevo(): void
    {
        Storage::fake('public');
        $this->admin();

        $salud = TipoDocumento::where('nombre', 'SCTR Salud')->first();
        $e1 = Empleado::create(['numero_documento' => '43745494', 'nombres' => 'Aarón', 'apellidos' => 'Carrión']);
        $doc = DocumentoCompartido::create(['fecha_vencimiento' => '2026-06-30']);
        $doc->coberturas()->create(['tipo_documento_id' => $salud->id, 'aseguradora' => 'Sanitas']);
        $doc->empleados()->sync([$e1->id]);

        $component = Volt::test('documentos-compartidos.tabla')->call('renovar', $doc->id);

        // Se abre como NUEVO (editandoId null) pero con coberturas y grupo copiados
        $component->assertSet('editandoId', null)
            ->assertSet('tiposSel', [(string) $salud->id])
            ->assertSet('empleadosSel', [(string) $e1->id]);
    }

    public function test_semaforo_cuenta_personas_por_cobertura(): void
    {
        $this->admin();
        $salud = TipoDocumento::where('nombre', 'SCTR Salud')->first();
        $pension = TipoDocumento::where('nombre', 'SCTR Pensión')->first();
        $e1 = Empleado::create(['numero_documento' => '45716686', 'nombres' => 'A', 'apellidos' => 'A']);
        $e2 = Empleado::create(['numero_documento' => '29426112', 'nombres' => 'B', 'apellidos' => 'B']);

        $doc = DocumentoCompartido::create(['fecha_vencimiento' => now()->subDay()->toDateString()]); // vencido
        $doc->coberturas()->createMany([
            ['tipo_documento_id' => $salud->id],
            ['tipo_documento_id' => $pension->id],
        ]);
        $doc->empleados()->sync([$e1->id, $e2->id]);

        $r = DocumentoCompartido::resumenSemaforo();
        $this->assertSame(4, $r['vencido']); // 2 personas × 2 coberturas
    }
}
