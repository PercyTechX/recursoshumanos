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

class LicenciasFlujoTest extends TestCase
{
    use RefreshDatabase;

    private Empleado $trabajador;
    private User $userTrabajador;
    private User $userSupervisor;
    private User $userRrhh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(CatalogoSeeder::class);

        // Supervisor (empleado + usuario)
        $supEmp = Empleado::create(['numero_documento' => '10000001', 'nombres' => 'Sup', 'apellidos' => 'Visor']);
        $this->userSupervisor = User::factory()->create();
        $this->userSupervisor->assignRole('Supervisor');
        $supEmp->forceFill(['user_id' => $this->userSupervisor->id])->save();

        // Trabajador (empleado con ese supervisor + usuario)
        $this->trabajador = Empleado::create(['numero_documento' => '20000002', 'nombres' => 'Ana', 'apellidos' => 'Díaz', 'supervisor_id' => $supEmp->id]);
        $this->userTrabajador = User::factory()->create();
        $this->userTrabajador->assignRole('Empleado');
        $this->trabajador->forceFill(['user_id' => $this->userTrabajador->id])->save();

        // RRHH
        $this->userRrhh = User::factory()->create();
        $this->userRrhh->assignRole('RRHH');
    }

    private function solicitud(string $estado = Ausencia::PENDIENTE_SUPERVISOR): Ausencia
    {
        return Ausencia::create([
            'empleado_id' => $this->trabajador->id, 'tipo' => Ausencia::PERMISO, 'con_goce' => true,
            'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-02', 'dias' => 2,
            'estado' => $estado, 'solicitado_por' => $this->userTrabajador->id,
        ]);
    }

    public function test_trabajador_solicita_licencia_queda_pendiente_supervisor(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userTrabajador);

        Volt::test('portal.index')
            ->call('abrirLicencia')
            ->set('lic_tipo', Ausencia::PERMISO)
            ->set('lic_inicio', '2026-08-10')
            ->set('lic_fin', '2026-08-11')
            ->set('lic_motivo', 'Trámite')
            ->call('solicitarLicencia')
            ->assertHasNoErrors();

        $a = Ausencia::first();
        $this->assertSame(Ausencia::PENDIENTE_SUPERVISOR, $a->estado);
        $this->assertSame($this->trabajador->id, $a->empleado_id);
        $this->assertSame(2, $a->dias);
    }

    public function test_descanso_medico_exige_sustento(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userTrabajador);

        Volt::test('portal.index')
            ->call('abrirLicencia')
            ->set('lic_tipo', Ausencia::DESCANSO_MEDICO)
            ->set('lic_inicio', '2026-08-10')
            ->set('lic_fin', '2026-08-12')
            ->call('solicitarLicencia')
            ->assertHasErrors(['lic_archivo']);

        $this->assertSame(0, Ausencia::count());
    }

    public function test_supervisor_visa_y_rrhh_aprueba(): void
    {
        $a = $this->solicitud();

        // Supervisor del trabajador visa → pendiente_rrhh
        $this->actingAs($this->userSupervisor);
        Volt::test('ausencias.tabla')->call('visar', $a->id)->assertHasNoErrors();
        $a->refresh();
        $this->assertSame(Ausencia::PENDIENTE_RRHH, $a->estado);
        $this->assertSame($this->userSupervisor->id, $a->visado_por);

        // RRHH aprueba → aprobada
        $this->actingAs($this->userRrhh);
        Volt::test('ausencias.tabla')->call('aprobar', $a->id)->assertHasNoErrors();
        $a->refresh();
        $this->assertSame(Ausencia::APROBADA, $a->estado);
        $this->assertSame($this->userRrhh->id, $a->decidida_por);
    }

    public function test_un_supervisor_ajeno_no_puede_visar(): void
    {
        $a = $this->solicitud();
        $otro = User::factory()->create();
        $otro->assignRole('Supervisor');
        Empleado::create(['numero_documento' => '30000003', 'nombres' => 'Otro', 'apellidos' => 'Sup', 'user_id' => $otro->id]);

        $this->actingAs($otro);
        Volt::test('ausencias.tabla')->call('visar', $a->id)->assertForbidden();
        $this->assertSame(Ausencia::PENDIENTE_SUPERVISOR, $a->fresh()->estado);
    }

    public function test_rrhh_rechaza_con_motivo(): void
    {
        $a = $this->solicitud(Ausencia::PENDIENTE_RRHH);
        $this->actingAs($this->userRrhh);

        Volt::test('ausencias.tabla')
            ->call('abrirRechazo', $a->id)
            ->set('rechazoMotivo', 'Falta el sustento')
            ->call('rechazar')
            ->assertHasNoErrors();

        $a->refresh();
        $this->assertSame(Ausencia::RECHAZADA, $a->estado);
        $this->assertSame('Falta el sustento', $a->comentario_decision);
    }

    public function test_trabajador_cancela_su_solicitud_pendiente(): void
    {
        $a = $this->solicitud();
        $this->actingAs($this->userTrabajador);

        Volt::test('portal.index')->call('cancelarLicencia', $a->id)->assertHasNoErrors();
        $this->assertSame(Ausencia::CANCELADA, $a->fresh()->estado);
    }
}
