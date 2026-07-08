<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoEpp extends Model
{
    protected $table = 'tipos_epp';

    protected $fillable = ['nombre', 'controla_talla', 'activo'];

    protected $casts = [
        'controla_talla' => 'boolean',
        'activo' => 'boolean',
    ];
}
