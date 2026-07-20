<?php

namespace Tests\Feature;

use App\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmpleadoCifradoTest extends TestCase
{
    use RefreshDatabase;

    public function test_datos_financieros_se_guardan_cifrados_y_se_leen_en_claro(): void
    {
        $emp = Empleado::create([
            'numero_documento' => '12345678', 'nombres' => 'Ana', 'apellidos' => 'Díaz',
            'sueldo' => '2500.00', 'numero_cuenta' => '191-98765432-0-11', 'cci' => '00219100987654320115',
        ]);

        // En BD (crudo) NO está el texto plano
        $raw = DB::table('empleados')->where('id', $emp->id)->first();
        $this->assertNotSame('2500.00', $raw->sueldo);
        $this->assertNotSame('191-98765432-0-11', $raw->numero_cuenta);
        $this->assertNotSame('00219100987654320115', $raw->cci);

        // El crudo es un valor cifrado por Laravel (se puede descifrar)
        $this->assertSame('191-98765432-0-11', Crypt::decryptString($raw->numero_cuenta));

        // Leído por el modelo, vuelve en claro
        $fresco = Empleado::find($emp->id);
        $this->assertSame('2500.00', $fresco->sueldo);
        $this->assertSame('191-98765432-0-11', $fresco->numero_cuenta);
        $this->assertSame('00219100987654320115', $fresco->cci);

        // El documento NO se cifra (es único/buscable)
        $this->assertSame('12345678', $raw->numero_documento);
    }

    public function test_valores_nulos_no_rompen(): void
    {
        $emp = Empleado::create(['numero_documento' => '87654321', 'nombres' => 'Luis', 'apellidos' => 'Pérez']);

        $this->assertNull($emp->fresh()->sueldo);
        $this->assertNull($emp->fresh()->numero_cuenta);
        $this->assertNull($emp->fresh()->cci);
    }
}
