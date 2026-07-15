<?php

namespace App\Services\SharePoint;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Sube / lee / borra archivos en SharePoint vía Microsoft Graph (app-only).
 * Reutilizable por cualquier módulo. Soporta varios "destinos" (biblioteca +
 * carpeta raíz), configurados en config('services.graph.destinos'). Ver docs/15-16.
 *
 * Ej. destinos: documentos → RRHH/Doc_Sistemas, rendiciones → CONTABILIDAD/Rend_Sistemas.
 * Guarda como referencia durable el driveItem id (sobrevive mover/renombrar).
 */
class SharePointDocs
{
    public function __construct(private GraphClient $graph) {}

    /** Nombre de la biblioteca (drive) del destino; cae al drive_name legado si no hay destino. */
    private function driveName(string $destino): string
    {
        return (string) (config("services.graph.destinos.{$destino}.drive") ?? config('services.graph.drive_name'));
    }

    /** Carpeta raíz del destino; cae al base_folder legado si no hay destino. */
    private function baseFolder(string $destino): string
    {
        return trim((string) (config("services.graph.destinos.{$destino}.folder") ?? config('services.graph.base_folder')), '/');
    }

    /** id de la biblioteca (drive) del destino, cacheado 12 h por nombre de biblioteca. */
    public function driveId(string $destino = 'documentos'): string
    {
        $driveName = $this->driveName($destino);

        return Cache::remember('graph.drive_id.'.$driveName, now()->addHours(12), function () use ($driveName) {
            $cfg = config('services.graph');
            $host = $cfg['site_host'];
            $path = ltrim((string) $cfg['site_path'], '/');

            $site = $this->graph->http()->get("/sites/{$host}:/{$path}");
            if ($site->failed()) {
                throw new RuntimeException('No se pudo resolver el sitio de SharePoint: '.$site->body());
            }
            $siteId = $site->json('id');

            $drives = $this->graph->http()->get("/sites/{$siteId}/drives");
            if ($drives->failed()) {
                throw new RuntimeException('No se pudieron listar las bibliotecas: '.$drives->body());
            }

            $drive = collect($drives->json('value', []))->firstWhere('name', $driveName);
            if (! $drive) {
                throw new RuntimeException("No existe la biblioteca «{$driveName}» en el sitio.");
            }

            return (string) $drive['id'];
        });
    }

    /**
     * Sube un archivo subido por el usuario (Livewire) a una carpeta del destino.
     *
     * @return array{item_id:string, web_url:string, name:string}
     */
    public function subir(UploadedFile $file, string $carpeta, ?string $nombre = null, string $destino = 'documentos'): array
    {
        return $this->subirContenido(
            file_get_contents($file->getRealPath()),
            $file->getMimeType() ?: 'application/octet-stream',
            $carpeta,
            $nombre ?? $file->getClientOriginalName(),
            $destino,
        );
    }

    /**
     * Sube contenido crudo a una carpeta del destino (usado también al reintentar
     * desde el archivo temporal local). Subida simple (apto para archivos < 250 MB).
     *
     * @return array{item_id:string, web_url:string, name:string}
     */
    public function subirContenido(string $contenido, string $mime, string $carpeta, string $nombre, string $destino = 'documentos'): array
    {
        $driveId = $this->driveId($destino);
        $base = $this->baseFolder($destino);
        $ruta = $this->rutaCodificada(($base !== '' ? $base.'/' : '').trim($carpeta, '/'), $this->sanitizar($nombre));

        $resp = $this->graph->http()
            ->withBody($contenido, $mime)
            ->put("/drives/{$driveId}/root:/{$ruta}:/content");

        if ($resp->status() === 401) {
            // token pudo expirar: reintenta una vez con token fresco
            $this->graph->olvidarToken();
            $resp = $this->graph->http()
                ->withBody($contenido, $mime)
                ->put("/drives/{$driveId}/root:/{$ruta}:/content");
        }

        if ($resp->failed()) {
            throw new RuntimeException('Error al subir a SharePoint ('.$resp->status().'): '.$resp->body());
        }

        return [
            'item_id' => (string) $resp->json('id'),
            'web_url' => (string) $resp->json('webUrl'),
            'name' => (string) $resp->json('name'),
        ];
    }

    /** Contenido binario de un archivo por su item id (para descargar/servir). */
    public function contenido(string $itemId, string $destino = 'documentos'): string
    {
        $driveId = $this->driveId($destino);
        $resp = $this->graph->http()->get("/drives/{$driveId}/items/{$itemId}/content");

        if ($resp->failed()) {
            throw new RuntimeException('Error al descargar de SharePoint ('.$resp->status().').');
        }

        return $resp->body();
    }

    /** Borra un archivo por su item id (mejor esfuerzo). */
    public function eliminar(string $itemId, string $destino = 'documentos'): void
    {
        $driveId = $this->driveId($destino);
        $this->graph->http()->delete("/drives/{$driveId}/items/{$itemId}");
    }

    /** Sanitiza el nombre para SharePoint (caracteres prohibidos y espacios extremos). */
    public function sanitizar(string $nombre): string
    {
        $nombre = str_replace(['#', '%', '*', ':', '<', '>', '?', '/', '\\', '|', '"'], '-', $nombre);
        $nombre = trim($nombre);

        return $nombre === '' ? 'archivo' : $nombre;
    }

    /** Codifica cada segmento de la ruta (respeta las barras como separadores). */
    private function rutaCodificada(string $carpeta, string $nombre): string
    {
        $partes = array_filter(explode('/', trim($carpeta, '/').'/'.$nombre), fn ($p) => $p !== '');

        return implode('/', array_map('rawurlencode', $partes));
    }
}
