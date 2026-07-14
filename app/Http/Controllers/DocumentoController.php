<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Services\SharePoint\SharePointDocs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoController extends Controller
{
    /**
     * Sirve el archivo de un documento sin exponer la URL directa: si está en
     * SharePoint lo transmite vía Graph (app-only); si quedó local, lo sirve del disco.
     */
    public function archivo(Documento $documento): Response
    {
        abort_unless(auth()->user()->can('documentos.ver'), 403);

        $nombre = $documento->archivo_nombre ?: 'documento';

        if ($documento->storage_driver === 'sharepoint' && $documento->sharepoint_item_id) {
            $contenido = app(SharePointDocs::class)->contenido($documento->sharepoint_item_id);

            return response($contenido, 200, [
                'Content-Type' => $this->mimePorNombre($nombre),
                'Content-Disposition' => 'inline; filename="'.addslashes($nombre).'"',
            ]);
        }

        abort_unless($documento->archivo_path && Storage::disk('public')->exists($documento->archivo_path), 404);

        return Storage::disk('public')->response($documento->archivo_path, $nombre, [
            'Content-Disposition' => 'inline',
        ]);
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

    /**
     * Exporta el listado de documentos (con su estado de semáforo) a CSV
     * que Excel abre directamente. Respeta los filtros de la pantalla.
     */
    public function exportar(Request $request): StreamedResponse
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $estado = (string) $request->query('estado', '');
        $tipo = (string) $request->query('tipo', '');

        $documentos = Documento::query()
            ->with(['empleado', 'tipoDocumento'])
            ->when($buscar, fn ($q) => $q->whereHas('empleado', fn ($e) => $e
                ->where('nombres', 'like', "%{$buscar}%")
                ->orWhere('apellidos', 'like', "%{$buscar}%")
                ->orWhere('numero_documento', 'like', "%{$buscar}%")))
            ->when($tipo, fn ($q) => $q->where('tipo_documento_id', $tipo))
            ->orderByRaw('fecha_vencimiento is null, fecha_vencimiento asc')
            ->get();

        if ($estado) {
            $documentos = $documentos->filter(fn ($d) => $d->estado === $estado)->values();
        }

        $etiquetas = [
            'vigente' => 'Vigente',
            'por_vencer' => 'Por vencer',
            'vencido' => 'Vencido',
            'sin_vigencia' => 'Sin vigencia',
        ];

        $nombreArchivo = 'documentos_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($documentos, $etiquetas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            fputcsv($out, ['Empleado', 'Documento', 'N° Doc. empleado', 'Emisión', 'Vencimiento', 'Estado', 'Días para vencer', 'Observación']);
            foreach ($documentos as $d) {
                fputcsv($out, [
                    trim("{$d->empleado?->apellidos} {$d->empleado?->nombres}"),
                    $d->tipoDocumento?->nombre,
                    $d->empleado?->numero_documento,
                    optional($d->fecha_emision)->format('d/m/Y'),
                    optional($d->fecha_vencimiento)->format('d/m/Y'),
                    $etiquetas[$d->estado] ?? $d->estado,
                    $d->dias_para_vencer,
                    $d->observacion,
                ]);
            }
            fclose($out);
        }, $nombreArchivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
