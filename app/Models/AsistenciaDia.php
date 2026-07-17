<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Overlay por empleado × día (ver docs/18): refrigerios y VB del supervisor.
 * Cada refrigerio (desayuno/almuerzo/cena) descuenta 60 min de las horas del día.
 */
class AsistenciaDia extends Model
{
    protected $table = 'asistencia_dias';

    protected $fillable = [
        'empleado_id', 'fecha', 'desayuno', 'almuerzo', 'cena',
        'vb_supervisor', 'vb_por', 'vb_at', 'marcado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'desayuno' => 'boolean',
        'almuerzo' => 'boolean',
        'cena' => 'boolean',
        'vb_supervisor' => 'boolean',
        'vb_at' => 'datetime',
    ];

    /** Minutos de refrigerio del día (60 por cada comida marcada). */
    public function getRefrigerioMinutosAttribute(): int
    {
        return 60 * ((int) $this->desayuno + (int) $this->almuerzo + (int) $this->cena);
    }

    /** Horas netas = brutas − refrigerios (nunca negativo). */
    public static function minutosNetos(int $brutos, int $refrigerioMinutos): int
    {
        return max(0, $brutos - $refrigerioMinutos);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function vbPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vb_por');
    }
}
