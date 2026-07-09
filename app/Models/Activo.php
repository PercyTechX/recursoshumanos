<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Activo extends Model
{
    protected $table = 'activos';

    protected $fillable = ['categoria_id', 'nombre', 'codigo', 'descripcion', 'costo', 'estado'];

    protected $casts = ['costo' => 'decimal:2'];

    // Estados
    public const DISPONIBLE = 'disponible';
    public const ASIGNADO = 'asignado';
    public const MANTENIMIENTO = 'mantenimiento';
    public const DE_BAJA = 'de_baja';
    public const PERDIDO = 'perdido';

    public const ESTADOS = [
        self::DISPONIBLE => 'Disponible',
        self::ASIGNADO => 'Asignado',
        self::MANTENIMIENTO => 'Mantenimiento',
        self::DE_BAJA => 'De baja',
        self::PERDIDO => 'Perdido',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaActivo::class, 'categoria_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class);
    }

    /** La asignación vigente (sin devolver), si existe. */
    public function asignacionActiva(): HasOne
    {
        return $this->hasOne(Asignacion::class)->whereNull('fecha_devolucion')->latestOfMany();
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }
}
