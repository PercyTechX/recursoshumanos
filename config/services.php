<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Microsoft Graph (app-only / client credentials) para documentos en SharePoint.
    // Ver docs/15-integracion-sharepoint-graph.md. Credenciales sensibles → .env, no commitear.
    'graph' => [
        'tenant_id' => env('GRAPH_TENANT_ID'),
        'client_id' => env('GRAPH_CLIENT_ID'),
        'client_secret' => env('GRAPH_CLIENT_SECRET'),
        'site_host' => env('GRAPH_SITE_HOST'),      // ej. gdsinfraestructura.sharepoint.com
        'site_path' => env('GRAPH_SITE_PATH'),      // ej. /sites/GDSINFRAESTRUCTURASAC
        'drive_name' => env('GRAPH_DRIVE_NAME'),    // biblioteca por defecto (legado / destino "documentos")
        // Carpeta raíz donde la app guarda TODO (para no ensuciar las carpetas manuales).
        'base_folder' => env('GRAPH_BASE_FOLDER', 'Doc_Sistemas'),
        // Destinos por módulo: biblioteca (drive) + carpeta raíz. Ver docs/16.
        'destinos' => [
            'documentos' => ['drive' => env('GRAPH_DRIVE_NAME', 'RRHH'), 'folder' => env('GRAPH_BASE_FOLDER', 'Doc_Sistemas')],
            'rendiciones' => ['drive' => env('GRAPH_DRIVE_RENDICIONES', 'CONTABILIDAD'), 'folder' => env('GRAPH_FOLDER_RENDICIONES', 'Rend_Sistemas')],
        ],
    ],

];
