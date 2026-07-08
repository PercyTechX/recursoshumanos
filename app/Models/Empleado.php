<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empleado extends Model
{
    use HasFactory;

    protected $table = 'empleados';

    protected $fillable = [
        'user_id', 'supervisor_id', 'area_id', 'cargo_id', 'sede_id',
        'tipo_documento', 'numero_documento', 'nombres', 'apellidos',
        'fecha_nacimiento', 'nacionalidad', 'telefono', 'correo', 'direccion', 'foto',
        'fecha_ingreso', 'tipo_contrato', 'tipo_trabajador', 'regimen_laboral',
        'sistema_pensionario', 'cuspp', 'regimen_salud', 'banco', 'numero_cuenta',
        'situacion', 'fecha_cese',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
        'fecha_cese' => 'date',
    ];

    // ---- Relaciones ----
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'supervisor_id');
    }

    public function subordinados(): HasMany
    {
        return $this->hasMany(Empleado::class, 'supervisor_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    // ---- Accessors ----
    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->apellidos} {$this->nombres}");
    }
}
