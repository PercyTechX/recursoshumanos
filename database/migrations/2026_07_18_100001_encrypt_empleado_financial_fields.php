<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cifra en reposo los datos financieros del empleado (revisión de BD #2):
 * numero_cuenta, cci y sueldo. Se ensanchan las columnas (el texto cifrado es
 * largo) y se cifran los valores existentes con la APP_KEY del entorno.
 *
 * NOTA: numero_documento NO se cifra (es único y se busca).
 * NOTA: al desplegar, esta migración cifra los datos que ya existan en prod.
 */
return new class extends Migration
{
    private array $cols = ['numero_cuenta', 'cci', 'sueldo'];

    public function up(): void
    {
        // El valor cifrado ocupa ~200+ chars → columnas TEXT.
        Schema::table('empleados', function (Blueprint $table) {
            $table->text('numero_cuenta')->nullable()->change();
            $table->text('cci')->nullable()->change();
            $table->text('sueldo')->nullable()->change();
        });

        // Cifrar los valores existentes (en crudo, sin pasar por el cast del modelo).
        foreach (DB::table('empleados')->select('id', ...$this->cols)->get() as $e) {
            $update = [];
            foreach ($this->cols as $col) {
                $val = $e->{$col};
                if (! is_null($val) && $val !== '' && ! $this->yaCifrado($val)) {
                    $update[$col] = Crypt::encryptString((string) $val);
                }
            }
            if ($update) {
                DB::table('empleados')->where('id', $e->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        // Descifrar de vuelta (mejor esfuerzo) y devolver los tipos originales.
        foreach (DB::table('empleados')->select('id', ...$this->cols)->get() as $e) {
            $update = [];
            foreach ($this->cols as $col) {
                if (! is_null($e->{$col}) && $this->yaCifrado($e->{$col})) {
                    try {
                        $update[$col] = Crypt::decryptString($e->{$col});
                    } catch (\Throwable) {
                        // dejar como está si no se puede descifrar
                    }
                }
            }
            if ($update) {
                DB::table('empleados')->where('id', $e->id)->update($update);
            }
        }

        Schema::table('empleados', function (Blueprint $table) {
            $table->string('numero_cuenta')->nullable()->change();
            $table->string('cci', 25)->nullable()->change();
            $table->decimal('sueldo', 10, 2)->nullable()->change();
        });
    }

    /** Heurística: los valores de Laravel cifrados son base64 de un JSON con iv/value/mac. */
    private function yaCifrado(string $valor): bool
    {
        $json = base64_decode($valor, true);
        if ($json === false) {
            return false;
        }
        $data = json_decode($json, true);

        return is_array($data) && isset($data['iv'], $data['value'], $data['mac']);
    }
};
