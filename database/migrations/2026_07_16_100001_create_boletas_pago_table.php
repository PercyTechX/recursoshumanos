<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boletas de pago (mínimo viable): RRHH sube el PDF por empleado/periodo y el
 * trabajador la ve en su portal y CONFIRMA su recepción (valor probatorio).
 * Archivo → SharePoint (destino documentos: Doc_Sistemas/{empleado}/Boletas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boletas_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('periodo');                        // primer día del mes (2026-07-01 = Julio 2026)
            $table->string('tipo', 30)->default('Mensual'); // Mensual | Gratificación | CTS | Utilidades | Otro
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recibida_at')->nullable();   // confirmación de recepción del trabajador

            // Archivo (SharePoint) + fallback local (guardar-temporal-y-reintentar)
            $table->string('archivo_item_id')->nullable();
            $table->string('archivo_web_url')->nullable();
            $table->string('archivo_status', 20)->nullable();
            $table->string('archivo_path')->nullable();
            $table->string('archivo_nombre')->nullable();

            $table->timestamps();

            $table->index(['empleado_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boletas_pago');
    }
};
