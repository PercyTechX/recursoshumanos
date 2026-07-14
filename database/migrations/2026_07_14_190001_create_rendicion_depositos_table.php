<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Depósitos de caja chica (módulo Rendiciones). Ver docs/16.
 * Reusa empleados (técnico), users (supervisor) y tickets. El "local" se toma
 * del ticket (snapshot). Archivos (voucher inicial, PDF resumen) van a SharePoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendicion_depositos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados');            // técnico beneficiario
            $table->foreignId('ticket_id')->constrained('tickets');                 // ticket del trabajo
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('monto', 10, 2)->default(0);   // total entregado (inicial + ampliaciones)
            $table->date('dia');                            // fecha del depósito inicial
            $table->string('token', 64)->unique();          // enlace del técnico (sin login)
            $table->string('estado', 20)->default('Rindiendo');
            $table->text('observaciones')->nullable();      // motivo de rechazo/anulación

            // Snapshots (para que el histórico no cambie si se edita la ficha)
            $table->string('tecnico_nombre')->nullable();
            $table->string('tecnico_celular')->nullable();
            $table->string('tecnico_documento')->nullable();
            $table->string('supervisor_nombre')->nullable();
            $table->string('local_nombre')->nullable();     // cliente + sucursal/sede del ticket

            $table->date('fecha_rendido')->nullable();      // VB° técnico (al liquidar)
            $table->date('fecha_aprobado')->nullable();     // VB° supervisor (al aprobar)

            // Voucher inicial (SharePoint) + fallback local
            $table->string('voucher_item_id')->nullable();
            $table->string('voucher_web_url')->nullable();
            $table->string('voucher_status', 20)->nullable();
            $table->string('voucher_path')->nullable();
            $table->string('voucher_nombre')->nullable();

            // Hoja Resumen PDF (SharePoint) + fallback local
            $table->string('resumen_item_id')->nullable();
            $table->string('resumen_web_url')->nullable();
            $table->string('resumen_status', 20)->nullable();
            $table->string('resumen_path')->nullable();

            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendicion_depositos');
    }
};
