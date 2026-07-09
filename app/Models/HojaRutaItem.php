<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HojaRutaItem extends Model
{
    protected $table = 'hoja_ruta_items';

    protected $fillable = [
        'hoja_ruta_id', 'activo_id', 'asignacion_id', 'devuelto',
        'estado_devolucion', 'monto_descuento', 'observacion',
    ];

    protected $casts = [
        'devuelto' => 'boolean',
        'monto_descuento' => 'decimal:2',
    ];

    public function hojaRuta(): BelongsTo
    {
        return $this->belongsTo(HojaRuta::class);
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(Asignacion::class);
    }
}
