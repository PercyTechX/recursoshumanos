<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Libro mayor (ledger) de vacaciones. El saldo = SUMA de días.
        // apertura: saldo inicial a la fecha de corte (+)
        // devengado: acumulación por tiempo trabajado (+)
        // gozado:    días tomados, al aprobar una solicitud (-)
        // ajuste:    corrección manual (+/-)
        Schema::create('movimientos_vacaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('tipo', 20); // apertura|devengado|gozado|ajuste
            $table->decimal('dias', 6, 2); // puede ser negativo (gozado/ajuste)
            $table->foreignId('solicitud_id')->nullable()->constrained('solicitudes_vacaciones')->nullOnDelete();
            $table->string('observacion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_vacaciones');
    }
};
