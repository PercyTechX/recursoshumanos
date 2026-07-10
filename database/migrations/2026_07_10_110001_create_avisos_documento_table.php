<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro de avisos al supervisor por documentos por vencer/vencidos.
        Schema::create('avisos_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->string('canal', 20)->default('whatsapp'); // whatsapp | correo
            $table->string('destino')->nullable();            // en WhatsApp lo elige el usuario al compartir
            $table->string('estado_documento', 20);           // por_vencer | vencido
            $table->integer('dias')->nullable();              // días para vencer (negativo si venció)
            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_documento');
    }
};
