<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoVacaciones extends Model
{
    protected $table = 'movimientos_vacaciones';

    protected $fillable = [
        'empleado_id', 'fecha', 'fecha_corte', 'tipo', 'dias', 'solicitud_id', 'observacion', 'created_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_corte' => 'date',
        'dias' => 'decimal:2',
    ];

    /** Días de vacaciones que se ganan por mes (régimen general Perú). */
    public const DEVENGO_MENSUAL = 2.5;

    public const APERTURA = 'apertura';
    public const DEVENGADO = 'devengado';
    public const GOZADO = 'gozado';
    public const REINTEGRO = 'reintegro';
    public const AJUSTE = 'ajuste';

    public const TIPOS = [self::APERTURA, self::DEVENGADO, self::GOZADO, self::REINTEGRO, self::AJUSTE];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudVacaciones::class, 'solicitud_id');
    }

    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            self::APERTURA => 'Apertura',
            self::DEVENGADO => 'Devengado',
            self::GOZADO => 'Gozado',
            self::REINTEGRO => 'Reintegro (retorno anticipado)',
            self::AJUSTE => 'Ajuste',
            default => ucfirst($this->tipo),
        };
    }
}
