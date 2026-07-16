<?php

namespace App\Services\Rendiciones;

use App\Models\RendicionDeposito;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Genera la "Hoja Resumen de Rendición" en PDF (A4) al aprobar un depósito.
 * Contenido según docs/ADAPTACION_PHP_LARAGON.md §7 + muestra real del usuario:
 * encabezado GDS, depósitos/ampliaciones, comprobantes, vuelto/reembolso,
 * VB° técnico y supervisor, pie "Elaborado por PercyTech". Ver docs/16 §8.
 */
class ResumenPdfService
{
    /** Devuelve el contenido binario del PDF. */
    public function generar(RendicionDeposito $deposito): string
    {
        $deposito->loadMissing(['ticket.cliente', 'gastos', 'liquidacion', 'ampliaciones']);

        return Pdf::loadView('pdf.rendicion-resumen', ['dep' => $deposito])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true) // incrusta solo los glifos usados (PDF liviano)
            ->output();
    }

    /** Nombre de archivo estándar del resumen (también usado al reintentar subida). */
    public static function nombreArchivo(RendicionDeposito $deposito): string
    {
        $ticket = $deposito->ticket?->ticket_atencion ?? ('DEP-'.$deposito->id);

        return 'Resumen_Rendicion_'.str_replace([' ', '/'], '_', $ticket).'.pdf';
    }
}
