<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Boleta de pago del trabajador (PDF subido por RRHH; el trabajador confirma recepción). */
class BoletaPago extends Model
{
    protected $table = 'boletas_pago';

    protected $fillable = [
        'empleado_id', 'periodo', 'tipo', 'subido_por', 'recibida_at',
        'archivo_item_id', 'archivo_web_url', 'archivo_status', 'archivo_path', 'archivo_nombre',
    ];

    protected $casts = [
        'periodo' => 'date',
        'recibida_at' => 'datetime',
    ];

    public const TIPOS = ['Mensual', 'Gratificación', 'CTS', 'Utilidades', 'Otro'];

    /** Etiqueta del periodo, ej. "Julio 2026". */
    public function getPeriodoLabelAttribute(): string
    {
        return ucfirst($this->periodo?->translatedFormat('F Y') ?? '—');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
