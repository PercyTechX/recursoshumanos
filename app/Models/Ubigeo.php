<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Ubigeo extends Model
{
    protected $table = 'ubigeos';

    public $timestamps = false;

    protected $fillable = ['codigo', 'departamento', 'provincia', 'distrito'];

    public static function departamentos(): Collection
    {
        return static::query()->distinct()->orderBy('departamento')->pluck('departamento');
    }

    public static function provincias(?string $departamento): Collection
    {
        if (! $departamento) {
            return collect();
        }

        return static::where('departamento', $departamento)->distinct()->orderBy('provincia')->pluck('provincia');
    }

    public static function distritos(?string $departamento, ?string $provincia): Collection
    {
        if (! $departamento || ! $provincia) {
            return collect();
        }

        return static::where('departamento', $departamento)->where('provincia', $provincia)
            ->orderBy('distrito')->pluck('distrito');
    }
}
