<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $fillable = [
        'ticket_atencion', 'cliente_id', 'sede_id', 'sucursal_id', 'descripcion',
        'estado', 'creado_por', 'cerrado_por', 'fecha_cierre',
    ];

    protected $casts = ['fecha_cierre' => 'datetime'];

    public const ABIERTO = 'abierto';
    public const CERRADO = 'cerrado';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    /** Ubicación del ticket (sucursal del cliente o nuestra sede) — es la geocerca. */
    public function ubicacion(): Sucursal|Sede|null
    {
        return $this->sucursal ?: $this->sede;
    }

    public function getUbicacionNombreAttribute(): string
    {
        if ($this->sucursal) {
            return $this->sucursal->nombre.' (sucursal)';
        }
        if ($this->sede) {
            return $this->sede->nombre.' (sede)';
        }

        return '—';
    }

    public function scopeAbiertos($query)
    {
        return $query->where('estado', self::ABIERTO);
    }
}
