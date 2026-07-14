<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Empleado;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentosSharePointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::flush();

        config()->set('services.graph', [
            'tenant_id' => 'tenant', 'client_id' => 'client', 'client_secret' => 'secret',
            'site_host' => 'test.sharepoint.com', 'site_path' => '/sites/RRHH', 'drive_name' => 'RRHH',
            'base_folder' => 'Doc_Sistemas',
        ]);
    }

    private function contexto(): array
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');
        $this->actingAs($user);

        $emp = Empleado::create(['numero_documento' => '12345678', 'nombres' => 'Ana', 'apellidos' => 'Díaz']);
        $tipo = TipoDocumento::query()->first();

        return [$emp, $tipo];
    }

    private function fakeGraphOk(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'faketoken', 'expires_in' => 3600]),
            'graph.microsoft.com/v1.0/sites/*/drives' => Http::response(['value' => [['id' => 'drive123', 'name' => 'RRHH']]]),
            'graph.microsoft.com/v1.0/sites/*' => Http::response(['id' => 'site123']),
            'graph.microsoft.com/v1.0/drives/*' => Http::response(['id' => 'item999', 'webUrl' => 'https://sp/rrhh/item', 'name' => 'contrato.pdf']),
        ]);
    }

    public function test_sube_documento_a_sharepoint(): void
    {
        Storage::fake('public');
        $this->fakeGraphOk();
        [$emp, $tipo] = $this->contexto();

        Volt::test('documentos.tabla')
            ->call('nuevo')
            ->set('empleado_id', $emp->id)
            ->set('tipo_documento_id', $tipo->id)
            ->set('archivo', UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf'))
            ->call('guardar')
            ->assertHasNoErrors();

        $doc = Documento::first();
        $this->assertSame('sharepoint', $doc->storage_driver);
        $this->assertSame('item999', $doc->sharepoint_item_id);
        $this->assertSame('https://sp/rrhh/item', $doc->sharepoint_web_url);
        $this->assertSame('subido', $doc->upload_status);
        $this->assertNull($doc->archivo_path); // el temporal local se borró

        // La subida cae bajo la carpeta raíz de la app + subcarpeta del empleado.
        Http::assertSent(fn ($req) => str_contains(rawurldecode($req->url()), 'root:/Doc_Sistemas/12345678 - Díaz Ana/')
            && str_ends_with(rawurldecode(explode('?', $req->url())[0]), ':/content'));
    }

    public function test_si_sharepoint_falla_el_documento_queda_pendiente(): void
    {
        Storage::fake('public');
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'faketoken', 'expires_in' => 3600]),
            'graph.microsoft.com/v1.0/sites/*/drives' => Http::response(['value' => [['id' => 'drive123', 'name' => 'RRHH']]]),
            'graph.microsoft.com/v1.0/sites/*' => Http::response(['id' => 'site123']),
            'graph.microsoft.com/v1.0/drives/*' => Http::response('boom', 500),
        ]);
        [$emp, $tipo] = $this->contexto();

        Volt::test('documentos.tabla')
            ->call('nuevo')
            ->set('empleado_id', $emp->id)
            ->set('tipo_documento_id', $tipo->id)
            ->set('archivo', UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf'))
            ->call('guardar')
            ->assertHasNoErrors();

        $doc = Documento::first();
        $this->assertSame('local', $doc->storage_driver);
        $this->assertSame('pendiente', $doc->upload_status);
        $this->assertNotNull($doc->archivo_path); // el archivo NO se perdió
        Storage::disk('public')->assertExists($doc->archivo_path);
        $this->assertNull($doc->sharepoint_item_id);
    }

    public function test_reintentar_sube_el_pendiente(): void
    {
        Storage::fake('public');
        [$emp, $tipo] = $this->contexto();

        // Documento pendiente con archivo local
        $path = UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf')->store('documentos', 'public');
        $doc = Documento::create([
            'empleado_id' => $emp->id, 'tipo_documento_id' => $tipo->id,
            'archivo_nombre' => 'contrato.pdf', 'archivo_path' => $path,
            'storage_driver' => 'local', 'upload_status' => 'pendiente',
        ]);

        $this->fakeGraphOk();

        Volt::test('documentos.tabla')
            ->call('reintentarSubida', $doc->id)
            ->assertHasNoErrors();

        $doc->refresh();
        $this->assertSame('sharepoint', $doc->storage_driver);
        $this->assertSame('subido', $doc->upload_status);
        $this->assertSame('item999', $doc->sharepoint_item_id);
        $this->assertNull($doc->archivo_path);
        Storage::disk('public')->assertMissing($path);
    }
}
