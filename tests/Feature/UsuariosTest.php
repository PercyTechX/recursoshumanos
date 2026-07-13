<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UsuariosTest extends TestCase
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

    public function test_superadmin_crea_usuario_con_rol_y_lo_vincula(): void
    {
        $this->actor('SuperAdmin');
        $empleado = Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Ana', 'apellidos' => 'Díaz']);

        Volt::test('usuarios.tabla')
            ->call('nuevo')
            ->set('name', 'Ana Díaz')
            ->set('email', 'ana@empresa.test')
            ->set('password', 'clave1234')
            ->set('roles', ['Empleado'])
            ->set('empleado_id', $empleado->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $u = User::where('email', 'ana@empresa.test')->first();
        $this->assertNotNull($u);
        $this->assertTrue($u->hasRole('Empleado'));
        $this->assertSame($u->id, $empleado->fresh()->user_id);
    }

    public function test_rrhh_no_puede_otorgar_superadmin(): void
    {
        $this->actor('RRHH');

        Volt::test('usuarios.tabla')
            ->call('nuevo')
            ->set('name', 'Intruso')
            ->set('email', 'intruso@empresa.test')
            ->set('password', 'clave1234')
            ->set('roles', ['SuperAdmin', 'Empleado'])
            ->call('guardar')
            ->assertHasNoErrors();

        $u = User::where('email', 'intruso@empresa.test')->first();
        $this->assertFalse($u->hasRole('SuperAdmin'));
        $this->assertTrue($u->hasRole('Empleado'));
    }

    public function test_restablecer_password(): void
    {
        $this->actor('SuperAdmin');
        $u = User::factory()->create(['password' => Hash::make('vieja12345')]);

        Volt::test('usuarios.tabla')
            ->call('abrirReset', $u->id)
            ->set('nuevaPassword', 'nueva12345')
            ->call('resetearPassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('nueva12345', $u->fresh()->password));
    }

    public function test_no_puede_desactivarse_a_si_mismo(): void
    {
        $yo = $this->actor('SuperAdmin');

        Volt::test('usuarios.tabla')->call('toggleActivo', $yo->id);

        $this->assertTrue($yo->fresh()->activo);
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $this->seed(CatalogoSeeder::class);
        $u = User::factory()->create(['password' => Hash::make('password'), 'activo' => false]);

        Volt::test('pages.auth.login')
            ->set('form.email', $u->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertGuest();
    }
}
