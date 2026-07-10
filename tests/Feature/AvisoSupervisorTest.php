<?php

namespace Tests\Feature;

use App\Mail\AvisoVencimientoDocumento;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_avisa_al_supervisor_por_correo(): void
    {
        Mail::fake();
        $this->admin();
        $supervisor = Empleado::create(['numero_documento' => '99999999', 'nombres' => 'Jefe', 'apellidos' => 'Pérez', 'correo' => 'jefe@empresa.test']);
        $doc = $this->docPorVencer($supervisor);

        Volt::test('documentos.tabla')->call('avisar', $doc->id)->assertHasNoErrors();

        Mail::assertSent(AvisoVencimientoDocumento::class, fn ($m) => $m->hasTo('jefe@empresa.test'));
        $this->assertDatabaseHas('avisos_documento', [
            'documento_id' => $doc->id, 'email_destino' => 'jefe@empresa.test', 'estado_documento' => 'por_vencer',
        ]);
    }

    public function test_no_avisa_si_no_hay_supervisor(): void
    {
        Mail::fake();
        $this->admin();
        $doc = $this->docPorVencer(null);

        Volt::test('documentos.tabla')->call('avisar', $doc->id);

        Mail::assertNothingSent();
        $this->assertSame(0, \App\Models\AvisoDocumento::count());
    }
}
