<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Comprobante de gasto de un depósito (módulo Rendiciones). */
class RendicionGasto extends Model
{
    protected $table = 'rendicion_gastos';

    protected $fillable = [
        'deposito_id', 'tipo_comprobante', 'nro_comprobante', 'monto_gasto', 'fecha_comprobante',
        'archivo_item_id', 'archivo_web_url', 'archivo_status', 'archivo_path', 'archivo_nombre',
    ];

    protected $casts = [
        'monto_gasto' => 'decimal:2',
        'fecha_comprobante' => 'date',
    ];

    public const TIPOS = ['Boleta', 'Factura', 'Recibo de Honorarios', 'Declaración Jurada', 'Otros'];

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(RendicionDeposito::class, 'deposito_id');
    }
}
