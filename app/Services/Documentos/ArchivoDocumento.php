<?php

namespace App\Services\Documentos;

use App\Models\Empleado;
use App\Services\SharePoint\SharePointDocs;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Prepara las columnas de archivo de un Documento: guarda temporal local e
 * intenta subirlo a SharePoint (Doc_Sistemas/{DNI - Apellidos Nombres}); si
 * Graph falla o no está configurado, queda "pendiente" (guardar-temporal-y-
 * reintentar). Reutilizado por: módulo Documentos, registro de empleado y
 * expediente. Ver docs/15.
 */
class ArchivoDocumento
{
    /** @return array<string, mixed> columnas archivo_* / sharepoint_* para el Documento */
    public function payload(UploadedFile $archivo, Empleado $empleado): array
    {
        $payload = [
            'archivo_nombre' => $archivo->getClientOriginalName(),
            'archivo_path' => $archivo->store('documentos', 'public'),
            'storage_driver' => 'local',
            'upload_status' => 'pendiente',
            'upload_error' => null,
        ];

        if (empty(config('services.graph.tenant_id'))) {
            return $payload; // sin Graph configurado (p.ej. tests): queda local/pendiente
        }

        try {
            $r = app(SharePointDocs::class)->subir(
                $archivo,
                $this->carpetaEmpleado($empleado),
                now()->format('Ymd_His').'_'.$archivo->getClientOriginalName(),
            );
            Storage::disk('public')->delete($payload['archivo_path']);
            $payload = array_merge($payload, [
                'archivo_path' => null,
                'storage_driver' => 'sharepoint',
                'sharepoint_item_id' => $r['item_id'],
                'sharepoint_web_url' => $r['web_url'],
                'upload_status' => 'subido',
            ]);
        } catch (\Throwable $e) {
            $payload['upload_error'] = Str::limit($e->getMessage(), 240);
            Log::warning('SharePoint: subida fallida, queda pendiente. '.$e->getMessage());
        }

        return $payload;
    }

    /** Subcarpeta del empleado en la biblioteca: {DNI - Apellidos Nombres}. */
    public function carpetaEmpleado(Empleado $emp): string
    {
        return trim(($emp->numero_documento ?? 's-d').' - '.($emp->apellidos ?? '').' '.($emp->nombres ?? ''));
    }
}
