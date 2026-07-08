<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoDocumento extends Model
{
    protected $table = 'tipos_documento';

    protected $fillable = ['nombre', 'dias_aviso_previo', 'requiere_vigencia', 'activo'];

    protected $casts = [
        'requiere_vigencia' => 'boolean',
        'activo' => 'boolean',
        'dias_aviso_previo' => 'integer',
    ];

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }
}
