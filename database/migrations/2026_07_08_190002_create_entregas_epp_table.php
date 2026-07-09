<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas_epp', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->foreignId('tipo_epp_id')->constrained('tipos_epp')->restrictOnDelete();

            $table->unsignedInteger('cantidad')->default(1);
            $table->string('talla')->nullable();
            $table->date('fecha');
            $table->string('firma_path')->nullable(); // acta de entrega (SST)
            $table->foreignId('entregado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion')->nullable();

            $table->timestamps();

            $table->index('empleado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas_epp');
    }
};
