<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolesPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actor(string $rol): User
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($rol);
        $this->actingAs($user);

        return $user;
    }

    public function test_defaults_asignan_permisos_por_rol(): void
    {
        $this->seed(CatalogoSeeder::class);
        $rrhh = Role::findByName('RRHH');
        $this->assertTrue($rrhh->hasPermissionTo('empleados.crear'));
        $this->assertTrue($rrhh->hasPermissionTo('usuarios.ver'));

        $contador = Role::findByName('Contador');
        $this->assertTrue($contador->hasPermissionTo('descuentos.aplicar'));
        $this->assertFalse($contador->hasPermissionTo('empleados.ver'));
    }

    public function test_superadmin_crea_rol_con_permisos(): void
    {
        $this->actor('SuperAdmin');

        Volt::test('roles.tabla')
            ->call('nuevo')
            ->set('nombre', 'Consulta')
            ->set('permisosSel', ['empleados.ver', 'documentos.ver'])
            ->call('guardar')
            ->assertHasNoErrors();

        $rol = Role::findByName('Consulta');
        $this->assertTrue($rol->hasPermissionTo('empleados.ver'));
        $this->assertFalse($rol->hasPermissionTo('empleados.crear'));
    }

    public function test_toggle_modulo_marca_todas_las_acciones(): void
    {
        $this->actor('SuperAdmin');

        Volt::test('roles.tabla')
            ->call('nuevo')
            ->call('toggleModulo', 'vacaciones')
            ->assertSet('permisosSel', ['vacaciones.ver', 'vacaciones.crear', 'vacaciones.aprobar', 'vacaciones.eliminar']);
    }

    public function test_no_se_elimina_rol_del_sistema(): void
    {
        $this->actor('SuperAdmin');
        $rrhh = Role::findByName('RRHH');

        Volt::test('roles.tabla')->call('eliminar', $rrhh->id);

        $this->assertNotNull(Role::find($rrhh->id));
    }

    public function test_boton_crear_se_oculta_sin_permiso(): void
    {
        // Rol solo lectura de empleados
        $this->seed(CatalogoSeeder::class);
        $rol = Role::create(['name' => 'Consulta', 'guard_name' => 'web']);
        $rol->givePermissionTo('empleados.ver');
        $user = User::factory()->create();
        $user->assignRole('Consulta');
        $this->actingAs($user);

        Volt::test('empleados.tabla')->assertDontSee('Nuevo');

        // RRHH sí ve el botón
        $rrhh = User::factory()->create();
        $rrhh->assignRole('RRHH');
        $this->actingAs($rrhh);
        Volt::test('empleados.tabla')->assertSee('Nuevo');
    }
}
