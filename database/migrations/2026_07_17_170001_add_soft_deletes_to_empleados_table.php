<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado lógico (archivar) de empleados: preserva el histórico legal
 * (marcaciones, documentos, boletas, rendiciones, etc.). El borrado físico
 * queda reservado al SuperAdmin y solo cuando NO hay histórico. Ver revisión de BD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
