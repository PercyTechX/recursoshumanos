<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hoja_ruta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hoja_ruta_id')->constrained('hojas_ruta')->cascadeOnDelete();
            $table->foreignId('activo_id')->nullable()->constrained('activos')->nullOnDelete();
            $table->foreignId('asignacion_id')->nullable()->constrained('asignaciones')->nullOnDelete();
            $table->boolean('devuelto')->default(true);
            $table->string('estado_devolucion')->nullable();
            $table->decimal('monto_descuento', 10, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hoja_ruta_items');
    }
};
