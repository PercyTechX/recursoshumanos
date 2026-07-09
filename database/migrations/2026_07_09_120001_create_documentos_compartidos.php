<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un tipo de documento puede amparar a varias personas con un solo archivo
        // (SCTR colectivo, homologación de sede, etc.).
        Schema::table('tipos_documento', function (Blueprint $table) {
            $table->boolean('compartible')->default(false)->after('requiere_vigencia');
        });

        // Documento que cubre a MUCHAS personas con un solo archivo y una vigencia.
        Schema::create('documentos_compartidos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('archivo_path')->nullable();
            $table->string('archivo_nombre')->nullable();
            $table->string('observacion')->nullable();
            $table->timestamps();
        });

        // Coberturas que ampara el documento (ej. SCTR Salud + SCTR Pensión),
        // cada una con su aseguradora y número de póliza opcionales.
        Schema::create('documento_compartido_cobertura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_compartido_id')->constrained('documentos_compartidos')->cascadeOnDelete();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->cascadeOnDelete();
            $table->string('aseguradora', 80)->nullable();
            $table->string('numero_poliza', 60)->nullable();
        });

        // Grupo de personas amparadas (selección).
        Schema::create('documento_compartido_empleado', function (Blueprint $table) {
            $table->foreignId('documento_compartido_id')->constrained('documentos_compartidos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->primary(['documento_compartido_id', 'empleado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_compartido_empleado');
        Schema::dropIfExists('documento_compartido_cobertura');
        Schema::dropIfExists('documentos_compartidos');

        Schema::table('tipos_documento', function (Blueprint $table) {
            $table->dropColumn('compartible');
        });
    }
};
