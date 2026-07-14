<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Comprobantes de gasto que el técnico sube a un depósito. Ver docs/16. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendicion_gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposito_id')->constrained('rendicion_depositos')->cascadeOnDelete();
            $table->string('tipo_comprobante');     // Boleta, Factura, Recibo de Honorarios, Declaración Jurada, Otros
            $table->string('nro_comprobante')->nullable();
            $table->decimal('monto_gasto', 10, 2);
            $table->date('fecha_comprobante');

            // Archivo del comprobante (SharePoint) + fallback local
            $table->string('archivo_item_id')->nullable();
            $table->string('archivo_web_url')->nullable();
            $table->string('archivo_status', 20)->nullable();
            $table->string('archivo_path')->nullable();
            $table->string('archivo_nombre')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendicion_gastos');
    }
};
