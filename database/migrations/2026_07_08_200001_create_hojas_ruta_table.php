<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hojas_ruta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('motivo'); // cese | perdida | otro
            $table->date('fecha');
            $table->string('firma_path')->nullable();
            $table->decimal('total_descuento', 10, 2)->default(0);
            $table->string('pdf_path')->nullable();
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('empleado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hojas_ruta');
    }
};
