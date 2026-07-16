<?php

namespace App\Console\Commands;

use App\Models\RendicionAmpliacion;
use App\Models\RendicionDeposito;
use App\Models\RendicionGasto;
use App\Models\RendicionLiquidacion;
use App\Services\Rendiciones\ResumenPdfService;
use App\Services\SharePoint\RendicionArchivos;
use Illuminate\Console\Command;

/**
 * Reintenta subir a SharePoint los archivos de rendiciones que quedaron
 * "pendientes" (guardar-temporal-y-reintentar). Ideal para un cron. Ver docs/16.
 */
class RendicionesSubirPendientes extends Command
{
    protected $signature = 'rendiciones:subir-pendientes';

    protected $description = 'Sube a SharePoint los vouchers/comprobantes de rendiciones pendientes';

    public function handle(RendicionArchivos $archivos): int
    {
        $ok = 0;
        $fail = 0;
        $tick = function (bool $subido) use (&$ok, &$fail) {
            $subido ? $ok++ : $fail++;
        };

        // Vouchers de depósitos
        foreach (RendicionDeposito::where('voucher_status', 'pendiente')->whereNotNull('voucher_path')->with('ticket')->get() as $dep) {
            $tick($archivos->subir($dep, 'voucher', $dep->carpetaSharePoint()));
        }
        // Comprobantes de gasto
        foreach (RendicionGasto::where('archivo_status', 'pendiente')->whereNotNull('archivo_path')->with('deposito.ticket')->get() as $g) {
            $tick($g->deposito ? $archivos->subir($g, 'archivo', $g->deposito->carpetaSharePoint()) : false);
        }
        // Vouchers de liquidación (devolución/reembolso)
        foreach (RendicionLiquidacion::where('comprobante_status', 'pendiente')->whereNotNull('comprobante_path')->with('deposito.ticket')->get() as $liq) {
            $tick($liq->deposito ? $archivos->subir($liq, 'comprobante', $liq->deposito->carpetaSharePoint()) : false);
        }
        // Vouchers de ampliaciones
        foreach (RendicionAmpliacion::where('voucher_status', 'pendiente')->whereNotNull('voucher_path')->with('deposito.ticket')->get() as $amp) {
            $tick($amp->deposito ? $archivos->subir($amp, 'voucher', $amp->deposito->carpetaSharePoint()) : false);
        }
        // Hojas Resumen PDF
        foreach (RendicionDeposito::where('resumen_status', 'pendiente')->whereNotNull('resumen_path')->with('ticket')->get() as $dep) {
            $tick($archivos->subir($dep, 'resumen', $dep->carpetaSharePoint(), ResumenPdfService::nombreArchivo($dep)));
        }

        $this->info("Subidos: {$ok} · Pendientes/errores: {$fail}");

        return self::SUCCESS;
    }
}
