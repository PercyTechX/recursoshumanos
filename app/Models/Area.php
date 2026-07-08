<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $table = 'areas';

    protected $fillable = ['nombre', 'parent_id', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /** Área padre (jerarquía). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'parent_id');
    }

    /** Sub-áreas. */
    public function subareas(): HasMany
    {
        return $this->hasMany(Area::class, 'parent_id');
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class);
    }
}
