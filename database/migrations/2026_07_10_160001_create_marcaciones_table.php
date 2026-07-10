<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marcaciones de asistencia (ingreso/salida) con GPS y metadato del equipo.
        Schema::create('marcaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('tipo', 10); // ingreso | salida
            $table->timestamp('fecha_hora');
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('precision_m', 8, 2)->nullable(); // exactitud del GPS en metros
            $table->string('user_agent')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('modelo_equipo')->nullable();
            // Registro manual del supervisor (Paso 5)
            $table->boolean('es_manual')->default(false);
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->index(['empleado_id', 'fecha_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marcaciones');
    }
};
