<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoController extends Controller
{
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
