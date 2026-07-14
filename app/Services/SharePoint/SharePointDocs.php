<?php

namespace App\Services\SharePoint;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Sube / lee / borra archivos en la biblioteca de SharePoint configurada,
 * vía Microsoft Graph (app-only). Reutilizable por cualquier módulo que
 * guarde archivos (documentos, expediente, ausencias, etc.). Ver docs/15.
 *
 * Guarda como referencia durable el driveItem id (sobrevive mover/renombrar).
 */
class SharePointDocs
{
    public function __construct(private GraphClient $graph) {}

    /** id de la biblioteca (drive) del sitio configurado, cacheado 12 h. */
    public function driveId(): string
    {
        return Cache::remember('graph.drive_id', now()->addHours(12), function () {
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

            $drive = collect($drives->json('value', []))->firstWhere('name', $cfg['drive_name']);
            if (! $drive) {
                throw new RuntimeException("No existe la biblioteca «{$cfg['drive_name']}» en el sitio.");
            }

            return (string) $drive['id'];
        });
    }

    /**
     * Sube un archivo subido por el usuario (Livewire) a una carpeta de la biblioteca.
     *
     * @return array{item_id:string, web_url:string, name:string}
     */
    public function subir(UploadedFile $file, string $carpeta, ?string $nombre = null): array
    {
        return $this->subirContenido(
            file_get_contents($file->getRealPath()),
            $file->getMimeType() ?: 'application/octet-stream',
            $carpeta,
            $nombre ?? $file->getClientOriginalName(),
        );
    }

    /**
     * Sube contenido crudo a una carpeta de la biblioteca (usado también al reintentar
     * desde el archivo temporal local). Subida simple (apto para archivos < 250 MB).
     *
     * @return array{item_id:string, web_url:string, name:string}
     */
    public function subirContenido(string $contenido, string $mime, string $carpeta, string $nombre): array
    {
        $driveId = $this->driveId();
        $ruta = $this->rutaCodificada($carpeta, $this->sanitizar($nombre));

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
    public function contenido(string $itemId): string
    {
        $driveId = $this->driveId();
        $resp = $this->graph->http()->get("/drives/{$driveId}/items/{$itemId}/content");

        if ($resp->failed()) {
            throw new RuntimeException('Error al descargar de SharePoint ('.$resp->status().').');
        }

        return $resp->body();
    }

    /** Borra un archivo por su item id (mejor esfuerzo). */
    public function eliminar(string $itemId): void
    {
        $driveId = $this->driveId();
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
