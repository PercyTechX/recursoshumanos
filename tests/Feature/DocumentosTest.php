<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function admin(): User
    {
        // DemoSeeder ejecuta CatalogoSeeder (roles + tipos) y crea datos demo.
        $this->seed(DemoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('RRHH');

        return $user;
    }

    public function test_el_indice_de_documentos_carga_para_rrhh(): void
    {
        $user = $this->admin();

        $this->actingAs($user)
            ->get('/documentos')
            ->assertOk()
            ->assertSee('Documentos')
            ->assertSee('Vigentes');
    }

    public function test_el_semaforo_calcula_los_estados_correctamente(): void
    {
        $this->admin();

        $docs = Documento::with('tipoDocumento')->get();

        $this->assertSame(4, $docs->where('estado', 'vigente')->count());
        $this->assertSame(3, $docs->where('estado', 'por_vencer')->count());
        $this->assertSame(3, $docs->where('estado', 'vencido')->count());
    }

    public function test_un_empleado_sin_permisos_no_accede(): void
    {
        $this->seed(DemoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Empleado');

        $this->actingAs($user)->get('/documentos')->assertForbidden();
    }

    public function test_la_exportacion_devuelve_un_csv(): void
    {
        $user = $this->admin();

        $resp = $this->actingAs($user)->get(route('documentos.exportar'));

        $resp->assertOk();
        $this->assertStringContainsString('text/csv', (string) $resp->headers->get('Content-Type'));
    }
}
