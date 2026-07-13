<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Marcacion extends Model
{
    protected $table = 'marcaciones';

    protected $fillable = [
        'empleado_id', 'tipo', 'fecha_hora', 'latitud', 'longitud', 'precision_m',
        'user_agent', 'ip', 'modelo_equipo', 'es_manual', 'registrado_por', 'motivo',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'es_manual' => 'boolean',
    ];

    public const INGRESO = 'ingreso';
    public const SALIDA = 'salida';

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function getTipoLabelAttribute(): string
    {
        return $this->tipo === self::INGRESO ? 'Ingreso' : 'Salida';
    }
}
