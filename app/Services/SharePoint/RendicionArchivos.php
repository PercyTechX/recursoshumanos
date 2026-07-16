<?php

namespace App\Services\SharePoint;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sube a SharePoint (destino "rendiciones" → CONTABILIDAD/Rend_Sistemas) los
 * archivos de rendiciones que quedaron guardados localmente. Patrón
 * guardar-temporal-y-reintentar: si Graph falla, el archivo NO se pierde (queda
 * local con estado "pendiente") y se puede reintentar. Ver docs/16.
 *
 * Cada modelo usa un juego de columnas con prefijo: {p}_path, {p}_nombre,
 * {p}_item_id, {p}_web_url, {p}_status. Ej. voucher_*, archivo_*, comprobante_*.
 */
class RendicionArchivos
{
    public function __construct(private SharePointDocs $sp) {}

    /**
     * Sube el archivo local del modelo (columnas {prefijo}_*) a rendiciones/{subcarpeta}.
     * No-op si no hay Graph configurado, si ya está subido o si no hay archivo local.
     * $nombre permite fijar el nombre en SharePoint cuando el modelo no tiene
     * columna {prefijo}_nombre (p.ej. el resumen PDF). Devuelve true si subió.
     */
    public function subir(Model $m, string $prefijo, string $subcarpeta, ?string $nombre = null): bool
    {
        if (empty(config('services.graph.tenant_id'))) {
            return false; // Graph no configurado (p.ej. en tests): se queda local/pendiente.
        }

        $path = $m->{$prefijo.'_path'};
        if (empty($path) || $m->{$prefijo.'_status'} === 'subido' || ! Storage::disk('public')->exists($path)) {
            return false;
        }

        try {
            $r = $this->sp->subirContenido(
                Storage::disk('public')->get($path),
                Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
                $subcarpeta,
                $nombre ?: ($m->{$prefijo.'_nombre'} ?: 'archivo'),
                'rendiciones',
            );

            Storage::disk('public')->delete($path);
            $m->forceFill([
                $prefijo.'_item_id' => $r['item_id'],
                $prefijo.'_web_url' => $r['web_url'],
                $prefijo.'_status' => 'subido',
                $prefijo.'_path' => null,
            ])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning("Rendiciones SharePoint [{$prefijo}]: ".$e->getMessage());

            return false;
        }
    }
}
