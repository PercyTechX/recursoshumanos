<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Depósitos adicionales (ampliaciones) que el supervisor agrega a un depósito. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendicion_ampliaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposito_id')->constrained('rendicion_depositos')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->date('fecha');
            $table->string('motivo')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supervisor_nombre')->nullable();

            // Voucher del depósito adicional (SharePoint) + fallback local
            $table->string('voucher_item_id')->nullable();
            $table->string('voucher_web_url')->nullable();
            $table->string('voucher_status', 20)->nullable();
            $table->string('voucher_path')->nullable();
            $table->string('voucher_nombre')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendicion_ampliaciones');
    }
};
