<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Descuento extends Model
{
    protected $table = 'descuentos';

    protected $fillable = [
        'empleado_id', 'hoja_ruta_id', 'activo_id', 'monto', 'motivo', 'estado', 'created_by',
    ];

    protected $casts = ['monto' => 'decimal:2'];

    public const PENDIENTE = 'pendiente';
    public const APLICADO = 'aplicado';

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function hojaRuta(): BelongsTo
    {
        return $this->belongsTo(HojaRuta::class);
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }
}
