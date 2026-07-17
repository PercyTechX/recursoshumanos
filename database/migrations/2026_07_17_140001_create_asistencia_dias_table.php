<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overlay por empleado × día para asistencia (ver docs/18): refrigerios
 * (desayuno/almuerzo/cena, restan 1h c/u de las horas trabajadas) y el
 * Visto Bueno del supervisor. NO toca la tabla `marcaciones`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencia_dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('fecha');

            $table->boolean('desayuno')->default(false);
            $table->boolean('almuerzo')->default(false);
            $table->boolean('cena')->default(false);

            $table->boolean('vb_supervisor')->default(false);
            $table->foreignId('vb_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vb_at')->nullable();

            $table->foreignId('marcado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empleado_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia_dias');
    }
};
