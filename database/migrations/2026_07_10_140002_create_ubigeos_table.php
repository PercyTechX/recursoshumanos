<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ubigeos del Perú (departamento → provincia → distrito) para listas dependientes.
        Schema::create('ubigeos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 6)->index();
            $table->string('departamento', 60);
            $table->string('provincia', 60);
            $table->string('distrito', 80);
            $table->index(['departamento', 'provincia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubigeos');
    }
};
