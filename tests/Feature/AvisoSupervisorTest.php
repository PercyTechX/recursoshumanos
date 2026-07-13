<?php

namespace Tests\Feature;

use App\Models\AvisoDocumento;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AvisoSupervisorTest extends TestCase
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

    private function docPorVencer(?Empleado $supervisor): Documento
    {
        $empleado = Empleado::create([
            'numero_documento' => '10101010', 'nombres' => 'Ana', 'apellidos' => 'Díaz',
            'supervisor_id' => $supervisor?->id,
        ]);
        $tipo = TipoDocumento::where('nombre', 'SCTR Salud')->first(); // aviso 30 días

        return Documento::create([
            'empleado_id' => $empleado->id,
            'tipo_documento_id' => $tipo->id,
            'fecha_vencimiento' => now()->addDays(10)->toDateString(), // por vencer
        ]);
    }

    public function test_registrar_aviso_deja_registro_whatsapp(): void
    {
        $this->admin();
        $supervisor = Empleado::create(['numero_documento' => '99999999', 'nombres' => 'Jefe', 'apellidos' => 'Pérez']);
        $doc = $this->docPorVencer($supervisor);

        Volt::test('documentos.tabla')->call('registrarAviso', $doc->id)->assertHasNoErrors();

        $this->assertDatabaseHas('avisos_documento', [
            'documento_id' => $doc->id, 'canal' => 'whatsapp',
            'estado_documento' => 'por_vencer', 'supervisor_id' => $supervisor->id,
        ]);
    }

    public function test_registrar_aviso_funciona_sin_supervisor(): void
    {
        $this->admin();
        $doc = $this->docPorVencer(null);

        Volt::test('documentos.tabla')->call('registrarAviso', $doc->id)->assertHasNoErrors();

        $this->assertSame(1, AvisoDocumento::count());
    }

    public function test_mensaje_whatsapp_incluye_los_datos(): void
    {
        $this->admin();
        $doc = $this->docPorVencer(null)->fresh(['empleado', 'tipoDocumento']);

        $msg = $doc->mensajeWhatsapp();
        $this->assertStringContainsString('SCTR Salud', $msg);
        $this->assertStringContainsString('Ana', $msg);
        $this->assertStringContainsString('wa.me/?text=', $doc->urlWhatsapp());
    }
}
