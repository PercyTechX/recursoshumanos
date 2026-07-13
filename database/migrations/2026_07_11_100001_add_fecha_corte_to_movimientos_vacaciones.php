<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_vacaciones', function (Blueprint $table) {
            // Solo en aperturas: fecha desde la cual devengan las vacaciones (2.5/mes).
            // Si es null, la apertura es un saldo fijo (no devenga).
            $table->date('fecha_corte')->nullable()->after('fecha');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_vacaciones', function (Blueprint $table) {
            $table->dropColumn('fecha_corte');
        });
    }
};
