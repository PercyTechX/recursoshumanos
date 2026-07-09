<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('descuentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->foreignId('hoja_ruta_id')->nullable()->constrained('hojas_ruta')->nullOnDelete();
            $table->foreignId('activo_id')->nullable()->constrained('activos')->nullOnDelete();
            $table->decimal('monto', 10, 2);
            $table->string('motivo')->nullable();
            $table->string('estado')->default('pendiente'); // pendiente | aplicado
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'empleado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('descuentos');
    }
};
