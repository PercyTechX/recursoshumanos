<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();

            // Vínculos
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->nullOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();

            // Datos personales
            $table->string('tipo_documento')->default('DNI');
            $table->string('numero_documento')->unique();
            $table->string('nombres');
            $table->string('apellidos');
            $table->date('fecha_nacimiento')->nullable();
            $table->string('nacionalidad')->default('Peruana');
            $table->string('telefono')->nullable();
            $table->string('correo')->nullable();
            $table->string('direccion')->nullable();
            $table->string('foto')->nullable(); // enlace/id en OneDrive (futuro)

            // Datos laborales
            $table->date('fecha_ingreso')->nullable();
            $table->string('tipo_contrato')->nullable();

            // Campos "T-Registro ready" (para futura planilla/SUNAT)
            $table->string('tipo_trabajador')->nullable();     // empleado, practicante, tercero...
            $table->string('regimen_laboral')->nullable();
            $table->string('sistema_pensionario')->nullable(); // ONP / AFP
            $table->string('cuspp')->nullable();                // código AFP
            $table->string('regimen_salud')->default('EsSalud');
            $table->string('banco')->nullable();
            $table->string('numero_cuenta')->nullable();

            // Estado
            $table->string('situacion')->default('activo');     // activo | cesado
            $table->date('fecha_cese')->nullable();

            $table->timestamps();

            $table->index(['situacion', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
