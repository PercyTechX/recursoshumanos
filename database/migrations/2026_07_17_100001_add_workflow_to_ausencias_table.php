<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo de solicitud + doble aprobación (Supervisor → RRHH) para Ausencias/Licencias.
 * Ver docs/17. Las ausencias existentes/registradas directas quedan "aprobada".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ausencias', function (Blueprint $table) {
            $table->string('estado', 24)->default('aprobada')->after('dias');
            $table->foreignId('solicitado_por')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('visado_por')->nullable()->after('solicitado_por')->constrained('users')->nullOnDelete();
            $table->date('fecha_visto')->nullable()->after('visado_por');
            $table->string('comentario_visto')->nullable()->after('fecha_visto');
            $table->foreignId('decidida_por')->nullable()->after('comentario_visto')->constrained('users')->nullOnDelete();
            $table->date('fecha_decision')->nullable()->after('decidida_por');
            $table->string('comentario_decision')->nullable()->after('fecha_decision');

            // Sustento en SharePoint (ya existen archivo_path / archivo_nombre para el fallback local)
            $table->string('archivo_item_id')->nullable()->after('archivo_nombre');
            $table->string('archivo_web_url')->nullable()->after('archivo_item_id');
            $table->string('archivo_status', 20)->nullable()->after('archivo_web_url');

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::table('ausencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitado_por');
            $table->dropConstrainedForeignId('visado_por');
            $table->dropConstrainedForeignId('decidida_por');
            $table->dropColumn([
                'estado', 'fecha_visto', 'comentario_visto', 'fecha_decision', 'comentario_decision',
                'archivo_item_id', 'archivo_web_url', 'archivo_status',
            ]);
        });
    }
};
