<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Ausencia extends Model
{
    protected $table = 'ausencias';

    protected $fillable = [
        'empleado_id', 'tipo', 'con_goce', 'fecha_inicio', 'fecha_fin', 'dias',
        'documento_ref', 'motivo', 'archivo_path', 'archivo_nombre', 'created_by',
        // Flujo de solicitud + doble aprobación (ver docs/17)
        'estado', 'solicitado_por', 'visado_por', 'fecha_visto', 'comentario_visto',
        'decidida_por', 'fecha_decision', 'comentario_decision',
        'archivo_item_id', 'archivo_web_url', 'archivo_status',
    ];

    protected $casts = [
        'con_goce' => 'boolean',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_visto' => 'date',
        'fecha_decision' => 'date',
    ];

    // ---- Tipos ----
    public const DESCANSO_MEDICO = 'descanso_medico';
    public const LICENCIA_CON_GOCE = 'licencia_con_goce';
    public const LICENCIA_SIN_GOCE = 'licencia_sin_goce';
    public const PERMISO = 'permiso';
    public const FALTA = 'falta';
    public const CITA_MEDICA = 'cita_medica';
    public const ENFERMEDAD_FAMILIAR = 'enfermedad_familiar';
    public const FALLECIMIENTO_FAMILIAR = 'fallecimiento_familiar';
    public const MATERNIDAD = 'maternidad';
    public const PATERNIDAD = 'paternidad';
    public const OTROS = 'otros';

    /** tipo => [label, con_goce por defecto, requiere_sustento, solicitable por el trabajador] */
    public const TIPOS = [
        self::CITA_MEDICA => ['Cita médica', true, false, true],
        self::DESCANSO_MEDICO => ['Descanso médico (CITT)', true, true, true],
        self::ENFERMEDAD_FAMILIAR => ['Enfermedad de familiar', true, true, true],
        self::FALLECIMIENTO_FAMILIAR => ['Fallecimiento de familiar', true, true, true],
        self::MATERNIDAD => ['Maternidad', true, true, true],
        self::PATERNIDAD => ['Paternidad', true, true, true],
        self::LICENCIA_CON_GOCE => ['Licencia con goce', true, false, true],
        self::LICENCIA_SIN_GOCE => ['Licencia sin goce', false, false, true],
        self::PERMISO => ['Permiso', true, false, true],
        self::OTROS => ['Otros', true, false, true],
        self::FALTA => ['Falta', false, false, false],
    ];

    // ---- Estados del flujo ----
    public const PENDIENTE_SUPERVISOR = 'pendiente_supervisor';
    public const PENDIENTE_RRHH = 'pendiente_rrhh';
    public const APROBADA = 'aprobada';
    public const RECHAZADA = 'rechazada';
    public const CANCELADA = 'cancelada';

    /** @return array<string, array{desde: array<int,string>, hasta: string}> */
    public static function transiciones(): array
    {
        return [
            'visar' => ['desde' => [self::PENDIENTE_SUPERVISOR], 'hasta' => self::PENDIENTE_RRHH],
            'aprobar' => ['desde' => [self::PENDIENTE_RRHH], 'hasta' => self::APROBADA],
            'rechazar' => ['desde' => [self::PENDIENTE_SUPERVISOR, self::PENDIENTE_RRHH], 'hasta' => self::RECHAZADA],
            'cancelar' => ['desde' => [self::PENDIENTE_SUPERVISOR, self::PENDIENTE_RRHH], 'hasta' => self::CANCELADA],
        ];
    }

    public function puede(string $accion): bool
    {
        $t = static::transiciones()[$accion] ?? null;

        return $t !== null && in_array($this->estado, $t['desde'], true);
    }

    public function transicionar(string $accion): void
    {
        if (! $this->puede($accion)) {
            throw new \DomainException("Transición '{$accion}' no permitida desde el estado '{$this->estado}'.");
        }
        $this->estado = static::transiciones()[$accion]['hasta'];
    }

    public function estaPendiente(): bool
    {
        return in_array($this->estado, [self::PENDIENTE_SUPERVISOR, self::PENDIENTE_RRHH], true);
    }

    // ---- Helpers de tipos ----
    public static function gocePorDefecto(string $tipo): bool
    {
        return self::TIPOS[$tipo][1] ?? true;
    }

    public static function requiereSustentoTipo(string $tipo): bool
    {
        return self::TIPOS[$tipo][2] ?? false;
    }

    public function requiereSustento(): bool
    {
        return static::requiereSustentoTipo($this->tipo);
    }

    /** Tipos que el trabajador puede solicitar desde su portal (excluye "falta"). */
    public static function solicitables(): array
    {
        return array_filter(self::TIPOS, fn ($t) => $t[3] ?? false);
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo][0] ?? ucfirst(str_replace('_', ' ', $this->tipo));
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            self::PENDIENTE_SUPERVISOR => 'Pendiente (supervisor)',
            self::PENDIENTE_RRHH => 'Pendiente (RRHH)',
            self::APROBADA => 'Aprobada',
            self::RECHAZADA => 'Rechazada',
            self::CANCELADA => 'Cancelada',
            default => ucfirst((string) $this->estado),
        };
    }

    // ---- Scopes ----
    public function scopePendientes(Builder $q): Builder
    {
        return $q->whereIn('estado', [self::PENDIENTE_SUPERVISOR, self::PENDIENTE_RRHH]);
    }

    // ---- Relaciones ----
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function visadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visado_por');
    }

    public function decididaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidida_por');
    }

    /** Días calendario (inclusivos) entre dos fechas. */
    public static function calcularDias(?string $inicio, ?string $fin): int
    {
        if (! $inicio || ! $fin) {
            return 0;
        }
        $i = Carbon::parse($inicio)->startOfDay();
        $f = Carbon::parse($fin)->startOfDay();

        return $f->lt($i) ? 0 : $i->diffInDays($f) + 1;
    }
}
