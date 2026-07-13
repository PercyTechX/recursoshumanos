<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación a Excel SIN dependencias externas (no requiere PhpSpreadsheet ni la
 * extensión ext-zip, que no siempre está disponible en hosting compartido / cPanel).
 *
 * Genera una hoja en formato "SpreadsheetML 2003" (XML de Excel). Ventajas frente a
 * un CSV: Excel la abre con las columnas YA separadas y con los números tipados
 * (ss:Type="Number" se interpreta siempre con punto decimal, independiente de la
 * configuración regional del equipo — en Perú el separador de listas es ';' y el
 * decimal es ',', lo que rompe los CSV con coma). Así los totales se pueden sumar
 * directamente en la planilla.
 */
class ExcelExport
{
    /**
     * @param  string          $filename  nombre sugerido (se le agrega .xls si falta)
     * @param  array<string>   $columnas  encabezados de la tabla
     * @param  iterable        $filas     cada fila es un array de celdas; un valor int|float
     *                                    se escribe como número, cualquier otro como texto
     * @param  string|null     $titulo    fila de título opcional sobre la tabla
     */
    public static function descargar(string $filename, array $columnas, iterable $filas, ?string $titulo = null): StreamedResponse
    {
        if (! str_ends_with(strtolower($filename), '.xls')) {
            $filename .= '.xls';
        }

        return response()->streamDownload(function () use ($columnas, $filas, $titulo) {
            $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $celda = function ($v) use ($esc) {
                if (is_int($v) || is_float($v)) {
                    return '<Cell><Data ss:Type="Number">'.$esc($v).'</Data></Cell>';
                }
                if ($v === null || $v === '') {
                    return '<Cell/>';
                }

                return '<Cell><Data ss:Type="String">'.$esc($v).'</Data></Cell>';
            };

            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<?mso-application progid="Excel.Sheet"?>'."\n";
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
                .' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Styles>'
                .'<Style ss:ID="hdr"><Font ss:Bold="1"/><Interior ss:Color="#EEEEEE" ss:Pattern="Solid"/></Style>'
                .'<Style ss:ID="ttl"><Font ss:Bold="1" ss:Size="13"/></Style>'
                .'</Styles>';
            echo '<Worksheet ss:Name="Reporte"><Table>';

            if ($titulo !== null) {
                echo '<Row><Cell ss:StyleID="ttl"><Data ss:Type="String">'.$esc($titulo).'</Data></Cell></Row>';
                echo '<Row/>';
            }

            echo '<Row>';
            foreach ($columnas as $c) {
                echo '<Cell ss:StyleID="hdr"><Data ss:Type="String">'.$esc($c).'</Data></Cell>';
            }
            echo '</Row>';

            foreach ($filas as $fila) {
                echo '<Row>';
                foreach ($fila as $valor) {
                    echo $celda($valor);
                }
                echo '</Row>';
            }

            echo '</Table></Worksheet></Workbook>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }
}
