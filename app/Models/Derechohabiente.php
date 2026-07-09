<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Derechohabiente extends Model
{
    protected $table = 'derechohabientes';

    protected $fillable = [
        'empleado_id', 'tipo', 'nombres', 'apellidos', 'tipo_documento',
        'numero_documento', 'fecha_nacimiento', 'parentesco', 'activo',
        'archivo_path', 'archivo_nombre',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo' => 'boolean',
    ];

    // Tipos de vínculo con derecho a EsSalud
    public const TIPOS = ['conyuge', 'conviviente', 'hijo', 'otro'];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            'conyuge' => 'Cónyuge',
            'conviviente' => 'Conviviente',
            'hijo' => 'Hijo(a)',
            default => 'Otro',
        };
    }
}
