<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CatalogosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(CatalogoSeeder::class);
    }

    private function actor(string $rol): User
    {
        $u = User::factory()->create();
        $u->assignRole($rol);
        $this->actingAs($u);

        return $u;
    }

    public function test_solo_superadmin_entra_a_catalogos(): void
    {
        $this->actor('RRHH');
        $this->get('/catalogos')->assertForbidden();

        $this->actor('SuperAdmin');
        $this->get('/catalogos')->assertOk()->assertSee('Catálogos');
    }

    public function test_superadmin_agrega_un_cargo(): void
    {
        $this->actor('SuperAdmin');

        Volt::test('catalogos.tabla')
            ->set('catalogo', 'cargos')
            ->call('nuevo')
            ->set('form.nombre', 'Gerente de TI')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertNotNull(Cargo::where('nombre', 'Gerente de TI')->first());
    }

    public function test_no_elimina_un_cargo_en_uso_pero_lo_puede_desactivar(): void
    {
        $this->actor('SuperAdmin');
        $cargo = Cargo::create(['nombre' => 'Operario', 'activo' => true]);
        Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Ana', 'apellidos' => 'Díaz', 'cargo_id' => $cargo->id]);

        // En uso → bloqueado
        Volt::test('catalogos.tabla')->set('catalogo', 'cargos')->call('eliminar', $cargo->id);
        $this->assertNotNull(Cargo::find($cargo->id));

        // Pero se puede desactivar
        Volt::test('catalogos.tabla')->set('catalogo', 'cargos')->call('toggleActivo', $cargo->id)->assertHasNoErrors();
        $this->assertFalse(Cargo::find($cargo->id)->activo);
    }

    public function test_elimina_un_cargo_sin_uso(): void
    {
        $this->actor('SuperAdmin');
        $cargo = Cargo::create(['nombre' => 'Cargo temporal', 'activo' => true]);

        Volt::test('catalogos.tabla')->set('catalogo', 'cargos')->call('eliminar', $cargo->id)->assertHasNoErrors();
        $this->assertNull(Cargo::find($cargo->id));
    }
}
