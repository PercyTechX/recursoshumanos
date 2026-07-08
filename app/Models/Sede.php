<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    protected $table = 'sedes';

    protected $fillable = ['nombre', 'direccion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class);
    }
}
