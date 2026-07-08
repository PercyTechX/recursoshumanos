<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaActivo extends Model
{
    protected $table = 'categorias_activo';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class, 'categoria_id');
    }
}
