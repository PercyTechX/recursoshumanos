<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            // Etiqueta: planilla (5ta) vs recibos por honorarios (4ta)
            $table->string('modalidad_pago', 20)->nullable()->after('regimen_laboral');
            // AFP específica (Integra, Prima, Profuturo, Habitat) — complementa sistema_pensionario
            $table->string('afp_nombre', 40)->nullable()->after('cuspp');
            // Estado de seguro: null = no registrado, true = con seguro, false = falta de seguro
            $table->boolean('tiene_seguro')->nullable()->after('regimen_salud');
        });

        Schema::create('derechohabientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('tipo', 20);            // conyuge | conviviente | hijo | otro
            $table->string('nombres', 120);
            $table->string('apellidos', 120)->nullable();
            $table->string('tipo_documento', 20)->default('DNI');
            $table->string('numero_documento', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('parentesco', 40)->nullable();
            $table->boolean('activo')->default(true);
            // Documento del derechohabiente (partida de nacimiento, DNI…). Motor de archivos completo = futuro.
            $table->string('archivo_path')->nullable();
            $table->string('archivo_nombre')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('derechohabientes');

        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn(['modalidad_pago', 'afp_nombre', 'tiene_seguro']);
        });
    }
};
