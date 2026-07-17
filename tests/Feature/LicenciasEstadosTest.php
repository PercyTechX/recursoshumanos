<?php

namespace Tests\Feature;

use App\Models\Ausencia;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LicenciasEstadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_flujo_doble_aprobacion(): void
    {
        $a = new Ausencia(['estado' => Ausencia::PENDIENTE_SUPERVISOR]);

        $this->assertTrue($a->puede('visar'));
        $this->assertTrue($a->puede('rechazar'));
        $this->assertTrue($a->puede('cancelar'));
        $this->assertFalse($a->puede('aprobar')); // aún no llega a RRHH

        $a->transicionar('visar');
        $this->assertSame(Ausencia::PENDIENTE_RRHH, $a->estado);
        $this->assertTrue($a->puede('aprobar'));
        $this->assertFalse($a->puede('visar'));

        $a->transicionar('aprobar');
        $this->assertSame(Ausencia::APROBADA, $a->estado);
        $this->assertFalse($a->estaPendiente());
    }

    public function test_rechazo_en_cualquier_paso(): void
    {
        $sup = new Ausencia(['estado' => Ausencia::PENDIENTE_SUPERVISOR]);
        $sup->transicionar('rechazar');
        $this->assertSame(Ausencia::RECHAZADA, $sup->estado);

        $rrhh = new Ausencia(['estado' => Ausencia::PENDIENTE_RRHH]);
        $rrhh->transicionar('rechazar');
        $this->assertSame(Ausencia::RECHAZADA, $rrhh->estado);
    }

    public function test_transicion_invalida_lanza(): void
    {
        $a = new Ausencia(['estado' => Ausencia::APROBADA]);
        $this->expectException(\DomainException::class);
        $a->transicionar('aprobar');
    }

    public function test_sustento_obligatorio_segun_tipo(): void
    {
        $this->assertTrue(Ausencia::requiereSustentoTipo(Ausencia::DESCANSO_MEDICO));
        $this->assertTrue(Ausencia::requiereSustentoTipo(Ausencia::MATERNIDAD));
        $this->assertTrue(Ausencia::requiereSustentoTipo(Ausencia::FALLECIMIENTO_FAMILIAR));
        $this->assertFalse(Ausencia::requiereSustentoTipo(Ausencia::CITA_MEDICA));
        $this->assertFalse(Ausencia::requiereSustentoTipo(Ausencia::PERMISO));
    }

    public function test_falta_no_es_solicitable_por_el_trabajador(): void
    {
        $solic = Ausencia::solicitables();
        $this->assertArrayHasKey(Ausencia::CITA_MEDICA, $solic);
        $this->assertArrayHasKey(Ausencia::MATERNIDAD, $solic);
        $this->assertArrayNotHasKey(Ausencia::FALTA, $solic);
    }

    public function test_permisos_visar_y_aprobar_existen(): void
    {
        $this->seed(CatalogoSeeder::class);
        $this->assertTrue(Permission::where('name', 'ausencias.visar')->exists());
        $this->assertTrue(Permission::where('name', 'ausencias.aprobar')->exists());
    }
}
