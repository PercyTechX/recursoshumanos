<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ausencias: descanso médico (CITT), licencias, permisos, faltas.
        Schema::create('ausencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('tipo', 30); // descanso_medico | licencia_con_goce | licencia_sin_goce | permiso | falta
            $table->boolean('con_goce')->default(true);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedSmallInteger('dias'); // días calendario (inclusivos)
            $table->string('documento_ref')->nullable(); // N° CITT / resolución / referencia
            $table->string('motivo')->nullable();
            $table->string('archivo_path')->nullable();
            $table->string('archivo_nombre')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencias');
    }
};
