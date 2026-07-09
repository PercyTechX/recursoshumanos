<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HojaRuta extends Model
{
    protected $table = 'hojas_ruta';

    protected $fillable = [
        'empleado_id', 'motivo', 'fecha', 'firma_path', 'total_descuento', 'pdf_path', 'generado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total_descuento' => 'decimal:2',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HojaRutaItem::class);
    }

    public function descuentos(): HasMany
    {
        return $this->hasMany(Descuento::class);
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }
}
