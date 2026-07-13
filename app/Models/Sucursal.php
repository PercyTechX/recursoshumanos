<?php

namespace App\Models;

use App\Models\Concerns\TieneGeocerca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sucursal extends Model
{
    use TieneGeocerca;

    protected $table = 'sucursales';

    protected $fillable = [
        'cliente_id', 'nombre', 'direccion', 'latitud', 'longitud', 'radio_metros',
        'departamento', 'provincia', 'distrito', 'centro_costo', 'activo',
    ];

    protected $casts = ['activo' => 'boolean', 'radio_metros' => 'integer'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
