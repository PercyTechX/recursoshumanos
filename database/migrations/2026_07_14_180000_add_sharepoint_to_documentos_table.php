<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas para almacenar los documentos en SharePoint (Microsoft Graph).
 * Ver docs/15. archivo_path/archivo_nombre se mantienen (temporal local + fallback).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('storage_driver', 20)->default('local')->after('archivo_nombre'); // local | sharepoint
            $table->string('sharepoint_item_id')->nullable()->after('storage_driver');
            $table->string('sharepoint_web_url')->nullable()->after('sharepoint_item_id');
            $table->string('upload_status', 20)->default('subido')->after('sharepoint_web_url'); // subido | pendiente | error
            $table->string('upload_error')->nullable()->after('upload_status');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn([
                'storage_driver', 'sharepoint_item_id', 'sharepoint_web_url',
                'upload_status', 'upload_error',
            ]);
        });
    }
};
