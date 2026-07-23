<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CertificadoTrabajoTest extends TestCase
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

    public function test_genera_pdf_para_trabajador_activo(): void
    {
        $this->rrhh();
        $cargo = Cargo::create(['nombre' => 'Técnico', 'activo' => true]);
        $area = Area::create(['nombre' => 'Operaciones', 'activo' => true]);
        $emp = Empleado::create([
            'numero_documento' => '44556677', 'nombres' => 'Carlos', 'apellidos' => 'Ruiz',
            'cargo_id' => $cargo->id, 'area_id' => $area->id,
            'fecha_ingreso' => '2024-03-01', 'situacion' => 'activo',
        ]);

        $resp = $this->get(route('empleados.certificado', $emp));
        $resp->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $resp->getContent());
    }

    public function test_certificado_disponible_para_cesado(): void
    {
        $this->rrhh();
        $emp = Empleado::create([
            'numero_documento' => '88990011', 'nombres' => 'Rosa', 'apellidos' => 'Vega',
            'fecha_ingreso' => '2022-01-10', 'situacion' => 'cesado', 'fecha_cese' => '2026-06-30',
        ]);

        $this->get(route('empleados.certificado', $emp))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_se_personaliza_por_genero(): void
    {
        $mujer = new Empleado(['nombres' => 'María', 'apellidos' => 'Quispe', 'numero_documento' => '41234567', 'sexo' => 'F', 'fecha_ingreso' => '2022-01-10', 'situacion' => 'activo']);
        $html = view('pdf.certificado-trabajo', ['empleado' => $mujer])->render();
        $this->assertStringContainsString('la señora', $html);
        $this->assertStringContainsString('identificada', $html);
        $this->assertStringContainsString('a solicitud de la interesada', $html);

        $hombre = new Empleado(['nombres' => 'Juan', 'apellidos' => 'Pérez', 'numero_documento' => '40404040', 'sexo' => 'M', 'fecha_ingreso' => '2022-01-10', 'situacion' => 'activo']);
        $htmlH = view('pdf.certificado-trabajo', ['empleado' => $hombre])->render();
        $this->assertStringContainsString('el señor', $htmlH);
        $this->assertStringContainsString('identificado con', $htmlH);
        $this->assertStringContainsString('a solicitud del interesado', $htmlH);
    }

    public function test_los_tipos_de_cese_existen(): void
    {
        $this->assertNotNull(TipoDocumento::where('nombre', 'Certificado de Trabajo')->first());
        $this->assertNotNull(TipoDocumento::where('nombre', 'Liquidación de Beneficios Sociales')->first());
    }

    public function test_sin_permiso_no_accede(): void
    {
        $u = User::factory()->create();
        $u->assignRole('Empleado');
        $this->actingAs($u);
        $emp = Empleado::create(['numero_documento' => '12312312', 'nombres' => 'X', 'apellidos' => 'Y']);

        $this->get(route('empleados.certificado', $emp))->assertForbidden();
    }
}
