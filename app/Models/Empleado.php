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
        'fecha_ingreso', 'tipo_contrato', 'tipo_trabajador', 'regimen_laboral', 'modalidad_pago', 'sueldo',
        'sistema_pensionario', 'cuspp', 'afp_nombre', 'regimen_salud', 'tiene_seguro',
        'banco', 'numero_cuenta', 'cci',
        'situacion', 'fecha_cese',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
        'fecha_cese' => 'date',
        'sueldo' => 'decimal:2',
        'tiene_seguro' => 'boolean',
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

    public function derechohabientes(): HasMany
    {
        return $this->hasMany(Derechohabiente::class);
    }

    /** Documentos colectivos (SCTR, pólizas) que amparan a este empleado. */
    public function documentosCompartidos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(DocumentoCompartido::class, 'documento_compartido_empleado');
    }

    public function ausencias(): HasMany
    {
        return $this->hasMany(Ausencia::class);
    }

    public function marcaciones(): HasMany
    {
        return $this->hasMany(Marcacion::class);
    }

    public function solicitudesVacaciones(): HasMany
    {
        return $this->hasMany(SolicitudVacaciones::class);
    }

    public function movimientosVacaciones(): HasMany
    {
        return $this->hasMany(MovimientoVacaciones::class);
    }

    /** Fecha de corte del devengo: la más reciente entre las aperturas que la tengan. */
    public function fechaCorteVacaciones(): ?\Illuminate\Support\Carbon
    {
        $corte = $this->movimientosVacaciones()
            ->where('tipo', MovimientoVacaciones::APERTURA)
            ->whereNotNull('fecha_corte')
            ->max('fecha_corte');

        return $corte ? \Illuminate\Support\Carbon::parse($corte) : null;
    }

    /**
     * Días devengados (calculados al vuelo) desde la fecha de corte hasta $hasta,
     * a razón de 2.5/mes prorrateado por días (mes = 30 días). 0 si no hay corte.
     */
    public function devengadoVacaciones(?\Illuminate\Support\Carbon $hasta = null): float
    {
        $corte = $this->fechaCorteVacaciones();
        if (! $corte) {
            return 0.0;
        }
        $hasta = ($hasta ?? now())->startOfDay();
        $dias = $corte->copy()->startOfDay()->diffInDays($hasta, false);
        if ($dias <= 0) {
            return 0.0;
        }

        return round($dias * MovimientoVacaciones::DEVENGO_MENSUAL / 30, 2);
    }

    /** Saldo de vacaciones = libro mayor (apertura + gozado ± ajuste) + devengado a la fecha. */
    public function getSaldoVacacionesAttribute(): float
    {
        return round((float) $this->movimientosVacaciones()->sum('dias') + $this->devengadoVacaciones(), 2);
    }

    // ---- Accessors ----
    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->apellidos} {$this->nombres}");
    }

    /** Cantidad de hijos registrados como derechohabientes. */
    public function getCantidadHijosAttribute(): int
    {
        return $this->derechohabientes()->where('tipo', 'hijo')->count();
    }
}
