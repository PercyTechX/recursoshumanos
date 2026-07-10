<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Avance de un ticket POR técnico (varios técnicos pueden reforzar el mismo).
        Schema::create('ticket_tecnico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('estado_trabajo', 20)->default('iniciado'); // iniciado|en_ejecucion|terminado|abortado
            $table->foreignId('liberado_por')->nullable()->constrained('users')->nullOnDelete(); // si lo liberó un supervisor
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'empleado_id']);
            $table->index(['empleado_id', 'estado_trabajo']);
        });

        // Bitácora de cada transición de estado (con GPS) — auditable.
        Schema::create('ticket_avances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_tecnico_id')->constrained('ticket_tecnico')->cascadeOnDelete();
            $table->string('estado', 20);
            $table->timestamp('fecha_hora');
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('precision_m', 8, 2)->nullable();
            $table->boolean('dentro_geocerca')->nullable();
            $table->boolean('es_manual')->default(false);
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_avances');
        Schema::dropIfExists('ticket_tecnico');
    }
};
