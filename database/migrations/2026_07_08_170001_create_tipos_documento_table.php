<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_documento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // Días de anticipación con los que se avisa antes del vencimiento
            $table->unsignedSmallInteger('dias_aviso_previo')->default(30);
            // Si el documento tiene fecha de vencimiento (ej. DNI no vence para esto)
            $table->boolean('requiere_vigencia')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_documento');
    }
};
