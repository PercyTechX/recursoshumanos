<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias_activo')->restrictOnDelete();
            $table->string('nombre');
            $table->string('codigo')->nullable()->unique(); // serie / código interno
            $table->text('descripcion')->nullable();
            $table->decimal('costo', 10, 2)->default(0);
            // disponible | asignado | mantenimiento | de_baja | perdido
            $table->string('estado')->default('disponible');
            $table->timestamps();

            $table->index(['estado', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activos');
    }
};
