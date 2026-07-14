<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liquidación del técnico (una por depósito): Exacto / Devolucion / Reembolso.
 * El comprobante es el voucher de devolución (técnico) o de reembolso (supervisor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendicion_liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposito_id')->unique()->constrained('rendicion_depositos')->cascadeOnDelete();
            $table->decimal('monto_depositado', 10, 2);
            $table->decimal('total_gastado', 10, 2);
            $table->decimal('diferencia', 10, 2);       // depositado - gastado (puede ser negativo)
            $table->string('estado_liquidacion', 20);   // Exacto | Devolucion | Reembolso

            // Voucher de devolución/reembolso (SharePoint) + fallback local
            $table->string('comprobante_item_id')->nullable();
            $table->string('comprobante_web_url')->nullable();
            $table->string('comprobante_status', 20)->nullable();
            $table->string('comprobante_path')->nullable();
            $table->string('comprobante_nombre')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendicion_liquidaciones');
    }
};
