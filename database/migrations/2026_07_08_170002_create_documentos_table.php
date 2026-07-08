<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();

            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();

            // Archivo escaneado. Hoy: disco local. Futuro: enlace/ID de OneDrive.
            $table->string('archivo_path')->nullable();
            $table->string('archivo_nombre')->nullable();

            $table->text('observacion')->nullable();

            $table->timestamps();

            // Se guarda historial: varios documentos por empleado + tipo
            $table->index(['empleado_id', 'tipo_documento_id']);
            $table->index('fecha_vencimiento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
