<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Depósito adicional (ampliación) que el supervisor agrega a un depósito. */
class RendicionAmpliacion extends Model
{
    protected $table = 'rendicion_ampliaciones';

    protected $fillable = [
        'deposito_id', 'monto', 'fecha', 'motivo', 'supervisor_id', 'supervisor_nombre',
        'voucher_item_id', 'voucher_web_url', 'voucher_status', 'voucher_path', 'voucher_nombre',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(RendicionDeposito::class, 'deposito_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
