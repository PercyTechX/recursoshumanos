<?php

namespace App\Models;

use App\Models\Concerns\TieneGeocerca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    use TieneGeocerca;

    protected $table = 'sedes';

    protected $fillable = ['nombre', 'tipo', 'direccion', 'latitud', 'longitud', 'radio_metros', 'activo'];

    protected $casts = ['activo' => 'boolean', 'radio_metros' => 'integer'];

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class);
    }
}
