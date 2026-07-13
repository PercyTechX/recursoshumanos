<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('ruc', 11)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->unsignedInteger('radio_metros')->default(100); // geocerca (editable)
            $table->string('departamento', 60)->nullable();
            $table->string('provincia', 60)->nullable();
            $table->string('distrito', 60)->nullable();
            $table->string('centro_costo', 60)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Nuestras sedes: geocerca + tipo
        Schema::table('sedes', function (Blueprint $table) {
            $table->string('tipo', 20)->nullable()->after('nombre'); // oficina | almacen | otro
            $table->decimal('latitud', 10, 7)->nullable()->after('direccion');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            $table->unsignedInteger('radio_metros')->default(100)->after('longitud');
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'latitud', 'longitud', 'radio_metros']);
        });
        Schema::dropIfExists('sucursales');
        Schema::dropIfExists('clientes');
    }
};
