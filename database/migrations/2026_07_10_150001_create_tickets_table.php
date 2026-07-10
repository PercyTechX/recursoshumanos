<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id(); // IDTICKET interno (correlativo del sistema)
            $table->string('ticket_atencion')->unique(); // manual, obligatorio, único
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            // Ubicación: una sede nuestra O una sucursal del cliente (exactamente una)
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('abierto'); // abierto | cerrado (ampliable)
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
