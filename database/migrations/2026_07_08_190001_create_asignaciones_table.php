<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();

            // Entrega
            $table->date('fecha_entrega');
            $table->string('firma_entrega_path')->nullable();
            $table->foreignId('entregado_por')->nullable()->constrained('users')->nullOnDelete();

            // Devolución (null = sigue asignado)
            $table->date('fecha_devolucion')->nullable();
            $table->string('estado_devolucion')->nullable(); // bueno | dañado | perdido
            $table->string('firma_devolucion_path')->nullable();
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();

            $table->text('observacion')->nullable();

            // Vínculo a hoja de ruta (se agrega la FK en el paso 5)
            $table->unsignedBigInteger('hoja_ruta_id')->nullable();

            $table->timestamps();

            $table->index('activo_id');
            $table->index('empleado_id');
            $table->index('hoja_ruta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
