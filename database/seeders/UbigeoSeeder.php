<?php

namespace Database\Seeders;

use App\Models\Ubigeo;
use Illuminate\Database\Seeder;

/**
 * Carga los ubigeos del Perú (departamento/provincia/distrito) desde
 * database/data/ubigeos.csv. Solo inserta si la tabla está vacía.
 */
class UbigeoSeeder extends Seeder
{
    public function run(): void
    {
        if (Ubigeo::query()->exists()) {
            return;
        }

        $ruta = database_path('data/ubigeos.csv');
        if (! is_file($ruta)) {
            return;
        }

        $fh = fopen($ruta, 'r');
        fgetcsv($fh); // cabecera
        $buffer = [];
        while (($fila = fgetcsv($fh)) !== false) {
            if (count($fila) < 4) {
                continue;
            }
            $buffer[] = [
                'codigo' => $fila[0],
                'departamento' => $fila[1],
                'provincia' => $fila[2],
                'distrito' => $fila[3],
            ];
            if (count($buffer) >= 500) {
                Ubigeo::insert($buffer);
                $buffer = [];
            }
        }
        if ($buffer) {
            Ubigeo::insert($buffer);
        }
        fclose($fh);
    }
}
