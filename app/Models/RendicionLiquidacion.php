<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Liquidación del técnico (una por depósito): Exacto / Devolucion / Reembolso. */
class RendicionLiquidacion extends Model
{
    protected $table = 'rendicion_liquidaciones';

    protected $fillable = [
        'deposito_id', 'monto_depositado', 'total_gastado', 'diferencia', 'estado_liquidacion',
        'comprobante_item_id', 'comprobante_web_url', 'comprobante_status', 'comprobante_path', 'comprobante_nombre',
    ];

    protected $casts = [
        'monto_depositado' => 'decimal:2',
        'total_gastado' => 'decimal:2',
        'diferencia' => 'decimal:2',
    ];

    /** Calcula el tipo de liquidación según la diferencia (depositado - gastado). */
    public static function tipoPorDiferencia(float $diferencia): string
    {
        return match (true) {
            abs($diferencia) < 0.005 => RendicionDeposito::LIQ_EXACTO,
            $diferencia > 0 => RendicionDeposito::LIQ_DEVOLUCION, // sobró dinero
            default => RendicionDeposito::LIQ_REEMBOLSO,          // gastó de más
        };
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(RendicionDeposito::class, 'deposito_id');
    }
}
