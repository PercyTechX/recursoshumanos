<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoDocumento extends Model
{
    protected $table = 'tipos_documento';

    protected $fillable = ['nombre', 'dias_aviso_previo', 'requiere_vigencia', 'compartible', 'activo'];

    protected $casts = [
        'requiere_vigencia' => 'boolean',
        'compartible' => 'boolean',
        'activo' => 'boolean',
        'dias_aviso_previo' => 'integer',
    ];

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    /** Tipos que un solo archivo puede amparar para varias personas (SCTR, homologación…). */
    public function scopeCompartibles($query)
    {
        return $query->where('compartible', true)->where('activo', true);
    }
}
