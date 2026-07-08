<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmpleadoController extends Controller
{
    /**
     * Exporta el listado de empleados a un archivo que Excel abre directamente.
     *
     * Respeta los filtros de búsqueda y situación enviados por query string.
     * (v1 en formato CSV con BOM UTF-8; se puede migrar a .xlsx con
     * maatwebsite/excel más adelante sin cambiar la interfaz.)
     */
    public function exportar(Request $request): StreamedResponse
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $situacion = (string) $request->query('situacion', '');

        $empleados = Empleado::query()
            ->with(['area', 'cargo', 'sede'])
            ->when($buscar, fn ($q) => $q->where(fn ($w) => $w
                ->where('nombres', 'like', "%{$buscar}%")
                ->orWhere('apellidos', 'like', "%{$buscar}%")
                ->orWhere('numero_documento', 'like', "%{$buscar}%")))
            ->when($situacion, fn ($q) => $q->where('situacion', $situacion))
            ->orderBy('apellidos')
            ->get();

        $nombreArchivo = 'empleados_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($empleados) {
            $out = fopen('php://output', 'w');
            // BOM para que Excel reconozca UTF-8 (tildes/ñ)
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Apellidos', 'Nombres', 'Tipo Doc', 'N° Documento', 'Área', 'Cargo', 'Sede', 'Fecha ingreso', 'Situación', 'Teléfono', 'Correo']);
            foreach ($empleados as $e) {
                fputcsv($out, [
                    $e->apellidos,
                    $e->nombres,
                    $e->tipo_documento,
                    $e->numero_documento,
                    $e->area?->nombre,
                    $e->cargo?->nombre,
                    $e->sede?->nombre,
                    optional($e->fecha_ingreso)->format('d/m/Y'),
                    ucfirst($e->situacion),
                    $e->telefono,
                    $e->correo,
                ]);
            }
            fclose($out);
        }, $nombreArchivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
