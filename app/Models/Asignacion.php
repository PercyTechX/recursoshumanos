<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asignacion extends Model
{
    protected $table = 'asignaciones';

    protected $fillable = [
        'activo_id', 'empleado_id', 'fecha_entrega', 'firma_entrega_path', 'entregado_por',
        'fecha_devolucion', 'estado_devolucion', 'firma_devolucion_path', 'recibido_por',
        'observacion', 'hoja_ruta_id',
    ];

    protected $casts = [
        'fecha_entrega' => 'date',
        'fecha_devolucion' => 'date',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    /** Asignaciones aún vigentes (sin devolver). */
    public function scopeActiva(Builder $query): Builder
    {
        return $query->whereNull('fecha_devolucion');
    }

    public function getEstaActivaAttribute(): bool
    {
        return $this->fecha_devolucion === null;
    }
}
