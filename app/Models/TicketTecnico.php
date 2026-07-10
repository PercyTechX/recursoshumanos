<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketTecnico extends Model
{
    protected $table = 'ticket_tecnico';

    protected $fillable = ['ticket_id', 'empleado_id', 'estado_trabajo', 'liberado_por', 'motivo'];

    public const INICIADO = 'iniciado';
    public const EN_EJECUCION = 'en_ejecucion';
    public const TERMINADO = 'terminado';
    public const ABORTADO = 'abortado';

    public const ACTIVOS = [self::INICIADO, self::EN_EJECUCION];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function avances(): HasMany
    {
        return $this->hasMany(TicketAvance::class);
    }

    public function estaActivo(): bool
    {
        return in_array($this->estado_trabajo, self::ACTIVOS, true);
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado_trabajo) {
            self::INICIADO => 'Iniciado',
            self::EN_EJECUCION => 'En ejecución',
            self::TERMINADO => 'Terminado',
            self::ABORTADO => 'Abortado',
            default => ucfirst($this->estado_trabajo),
        };
    }
}
