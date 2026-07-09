<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('sexo')->nullable()->after('fecha_nacimiento');
            $table->string('estado_civil')->nullable()->after('sexo');
            // Planilla
            $table->decimal('sueldo', 10, 2)->nullable()->after('regimen_laboral');
            // Bancario
            $table->string('cci', 25)->nullable()->after('numero_cuenta');
            // Contacto de emergencia
            $table->string('emergencia_nombre')->nullable();
            $table->string('emergencia_parentesco')->nullable();
            $table->string('emergencia_telefono')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn([
                'sexo', 'estado_civil', 'sueldo', 'cci',
                'emergencia_nombre', 'emergencia_parentesco', 'emergencia_telefono',
            ]);
        });
    }
};
