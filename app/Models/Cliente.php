<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = ['razon_social', 'nombre_comercial', 'ruc', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function sucursales(): HasMany
    {
        return $this->hasMany(Sucursal::class);
    }

    public function getNombreAttribute(): string
    {
        return $this->nombre_comercial ?: $this->razon_social;
    }
}
