<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Depósito de caja chica (módulo Rendiciones). Ver docs/16.
 * Contiene la máquina de estados (respetar exactamente docs/ADAPTACION_PHP_LARAGON.md §3).
 */
class RendicionDeposito extends Model
{
    protected $table = 'rendicion_depositos';

    protected $fillable = [
        'empleado_id', 'ticket_id', 'supervisor_id', 'monto', 'dia', 'token', 'estado',
        'observaciones', 'tecnico_nombre', 'tecnico_celular', 'tecnico_documento',
        'supervisor_nombre', 'local_nombre', 'fecha_rendido', 'fecha_aprobado',
        'voucher_item_id', 'voucher_web_url', 'voucher_status', 'voucher_path', 'voucher_nombre',
        'resumen_item_id', 'resumen_web_url', 'resumen_status', 'resumen_path',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'dia' => 'date',
        'fecha_rendido' => 'date',
        'fecha_aprobado' => 'date',
    ];

    // ---- Estados (valores EXACTOS en BD) ----
    public const RINDIENDO = 'Rindiendo';
    public const POR_REVISAR = 'Por Revisar';
    public const FINALIZADO = 'Finalizado';
    public const OBSERVADO = 'Observado';
    public const ANULADO = 'Anulado';

    // ---- Tipos de liquidación ----
    public const LIQ_EXACTO = 'Exacto';
    public const LIQ_DEVOLUCION = 'Devolucion';
    public const LIQ_REEMBOLSO = 'Reembolso';

    /** @return array<int,string> */
    public static function estados(): array
    {
        return [self::RINDIENDO, self::POR_REVISAR, self::FINALIZADO, self::OBSERVADO, self::ANULADO];
    }

    /**
     * Transiciones permitidas: acción => {desde: estados válidos, hasta: estado resultante
     * (null = no cambia el estado, p.ej. "ampliar")}.
     *
     * @return array<string, array{desde: array<int,string>, hasta: ?string}>
     */
    public static function transiciones(): array
    {
        return [
            'liquidar' => ['desde' => [self::RINDIENDO, self::OBSERVADO], 'hasta' => self::POR_REVISAR],
            'aprobar' => ['desde' => [self::POR_REVISAR], 'hasta' => self::FINALIZADO],
            'rechazar' => ['desde' => [self::POR_REVISAR], 'hasta' => self::OBSERVADO],
            'anular' => ['desde' => [self::RINDIENDO, self::POR_REVISAR, self::OBSERVADO], 'hasta' => self::ANULADO],
            'ampliar' => ['desde' => [self::RINDIENDO, self::OBSERVADO], 'hasta' => null],
        ];
    }

    /** ¿La acción es válida desde el estado actual? */
    public function puede(string $accion): bool
    {
        $t = static::transiciones()[$accion] ?? null;

        return $t !== null && in_array($this->estado, $t['desde'], true);
    }

    /**
     * Aplica el cambio de estado de una acción (sin efectos colaterales: fechas, archivos
     * y PDF los maneja el servicio/componente). Lanza si la transición no es válida.
     */
    public function transicionar(string $accion): void
    {
        if (! $this->puede($accion)) {
            throw new \DomainException("Transición '{$accion}' no permitida desde el estado '{$this->estado}'.");
        }
        $hasta = static::transiciones()[$accion]['hasta'];
        if ($hasta !== null) {
            $this->estado = $hasta;
        }
    }

    /** Token de 32 caracteres hex (128 bits) para el enlace del técnico. */
    public static function nuevoToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    // ---- Derivados ----

    /** Monto del depósito inicial = total - suma de ampliaciones. */
    public function getMontoInicialAttribute(): float
    {
        return round((float) $this->monto - (float) $this->ampliaciones->sum('monto'), 2);
    }

    public function getEditablePorTecnicoAttribute(): bool
    {
        return in_array($this->estado, [self::RINDIENDO, self::OBSERVADO], true);
    }

    // ---- Scopes ----
    public function scopePendientes(Builder $q): Builder
    {
        return $q->whereIn('estado', [self::RINDIENDO, self::POR_REVISAR, self::OBSERVADO]);
    }

    // ---- Relaciones ----
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(RendicionGasto::class, 'deposito_id');
    }

    public function liquidacion(): HasOne
    {
        return $this->hasOne(RendicionLiquidacion::class, 'deposito_id');
    }

    public function ampliaciones(): HasMany
    {
        return $this->hasMany(RendicionAmpliacion::class, 'deposito_id');
    }
}
