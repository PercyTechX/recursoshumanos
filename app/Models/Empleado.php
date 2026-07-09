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
        'fecha_nacimiento', 'sexo', 'estado_civil', 'nacionalidad', 'telefono', 'correo', 'direccion', 'foto',
        'emergencia_nombre', 'emergencia_parentesco', 'emergencia_telefono',
        'fecha_ingreso', 'tipo_contrato', 'tipo_trabajador', 'regimen_laboral', 'sueldo',
        'sistema_pensionario', 'cuspp', 'regimen_salud', 'banco', 'numero_cuenta', 'cci',
        'situacion', 'fecha_cese',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
        'fecha_cese' => 'date',
        'sueldo' => 'decimal:2',
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

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class);
    }

    /** Activos que tiene en su poder ahora (sin devolver). */
    public function asignacionesActivas(): HasMany
    {
        return $this->hasMany(Asignacion::class)->whereNull('fecha_devolucion');
    }

    public function entregasEpp(): HasMany
    {
        return $this->hasMany(EntregaEpp::class);
    }

    public function hojasRuta(): HasMany
    {
        return $this->hasMany(HojaRuta::class);
    }

    public function descuentos(): HasMany
    {
        return $this->hasMany(Descuento::class);
    }

    // ---- Accessors ----
    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->apellidos} {$this->nombres}");
    }
}
