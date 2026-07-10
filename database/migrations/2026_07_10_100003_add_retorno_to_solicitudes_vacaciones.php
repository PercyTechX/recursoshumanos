<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_vacaciones', function (Blueprint $table) {
            // Interrupción de vacaciones: la empresa lo hace volver antes.
            $table->date('fecha_fin_real')->nullable()->after('fecha_fin');       // último día real de vacaciones
            $table->decimal('dias_reintegrados', 6, 2)->nullable()->after('dias'); // días que volvieron al saldo
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_vacaciones', function (Blueprint $table) {
            $table->dropColumn(['fecha_fin_real', 'dias_reintegrados']);
        });
    }
};
