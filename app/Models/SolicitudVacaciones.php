<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudVacaciones extends Model
{
    protected $table = 'solicitudes_vacaciones';

    protected $fillable = [
        'empleado_id', 'fecha_inicio', 'fecha_fin', 'fecha_fin_real', 'dias', 'dias_reintegrados', 'motivo',
        'estado', 'decidida_por', 'fecha_decision', 'comentario_decision', 'created_by',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_fin_real' => 'date',
        'fecha_decision' => 'date',
        'dias_reintegrados' => 'decimal:2',
    ];

    public const PENDIENTE = 'pendiente';
    public const APROBADA = 'aprobada';
    public const RECHAZADA = 'rechazada';
    public const CANCELADA = 'cancelada';

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function decididaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidida_por');
    }

    public function movimiento(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MovimientoVacaciones::class, 'solicitud_id');
    }

    /** Días calendario (inclusivos) entre dos fechas. */
    public static function calcularDias(?string $inicio, ?string $fin): int
    {
        if (! $inicio || ! $fin) {
            return 0;
        }
        $i = \Illuminate\Support\Carbon::parse($inicio)->startOfDay();
        $f = \Illuminate\Support\Carbon::parse($fin)->startOfDay();
        if ($f->lt($i)) {
            return 0;
        }

        return $i->diffInDays($f) + 1;
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            self::APROBADA => 'Aprobada',
            self::RECHAZADA => 'Rechazada',
            self::CANCELADA => 'Cancelada',
            default => 'Pendiente',
        };
    }

    /** ¿Se interrumpió (retorno anticipado)? */
    public function getInterrumpidaAttribute(): bool
    {
        return $this->fecha_fin_real !== null;
    }
}
