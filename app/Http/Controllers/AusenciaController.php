<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Services\SharePoint\SharePointDocs;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AusenciaController extends Controller
{
    /** Sirve el sustento para RRHH/supervisor (permiso ausencias.ver). */
    public function sustento(Ausencia $ausencia): Response
    {
        abort_unless(auth()->user()->can('ausencias.ver'), 403);

        return $this->servir($ausencia);
    }

    /** Transmite el archivo del sustento desde SharePoint o del disco local. */
    public function servir(Ausencia $ausencia): Response
    {
        $nombre = $ausencia->archivo_nombre ?: 'sustento';

        if ($ausencia->archivo_status === 'subido' && $ausencia->archivo_item_id) {
            $contenido = app(SharePointDocs::class)->contenido($ausencia->archivo_item_id, 'documentos');

            return response($contenido, 200, [
                'Content-Type' => $this->mimePorNombre($nombre),
                'Content-Disposition' => 'inline; filename="'.addslashes($nombre).'"',
            ]);
        }

        abort_unless($ausencia->archivo_path && Storage::disk('public')->exists($ausencia->archivo_path), 404);

        return Storage::disk('public')->response($ausencia->archivo_path, $nombre, ['Content-Disposition' => 'inline']);
    }

    private function mimePorNombre(string $nombre): string
    {
        return match (strtolower(pathinfo($nombre, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}
