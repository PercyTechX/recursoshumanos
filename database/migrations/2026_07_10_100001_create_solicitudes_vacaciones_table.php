<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_vacaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedSmallInteger('dias'); // días calendario (inclusivos)
            $table->string('motivo')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente|aprobada|rechazada|cancelada
            $table->foreignId('decidida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_decision')->nullable();
            $table->string('comentario_decision')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_vacaciones');
    }
};
