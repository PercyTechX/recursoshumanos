<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Documento extends Model
{
    protected $table = 'documentos';

    protected $fillable = [
        'empleado_id', 'tipo_documento_id', 'fecha_emision', 'fecha_vencimiento',
        'archivo_path', 'archivo_nombre', 'observacion',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    // Estados del semáforo
    public const VIGENTE = 'vigente';
    public const POR_VENCER = 'por_vencer';
    public const VENCIDO = 'vencido';
    public const SIN_VIGENCIA = 'sin_vigencia';

    // ---- Relaciones ----
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function avisos(): HasMany
    {
        return $this->hasMany(AvisoDocumento::class);
    }

    // ---- Aviso por WhatsApp ----

    /** Texto del aviso listo para compartir por WhatsApp. */
    public function mensajeWhatsapp(): string
    {
        $emp = $this->empleado;
        $tipo = $this->tipoDocumento?->nombre ?? 'Documento';
        $venc = optional($this->fecha_vencimiento)->format('d/m/Y') ?? 's/f';
        $dias = $this->dias_para_vencer;

        $estado = $this->estado === 'vencido'
            ? ($dias !== null ? 'VENCIDO hace '.abs($dias).' día(s)' : 'VENCIDO')
            : ($dias !== null ? 'por vencer en '.$dias.' día(s)' : 'por vencer');

        $lineas = [
            '*Aviso RRHH — Documento '.($this->estado === 'vencido' ? 'vencido' : 'por vencer').'*',
            'Trabajador: '.trim(($emp?->nombres ?? '').' '.($emp?->apellidos ?? '')),
            'Documento: '.$tipo,
            'Vencimiento: '.$venc.' ('.$estado.')',
        ];
        if ($emp?->supervisor) {
            $lineas[] = 'Supervisor: '.$emp->supervisor->nombres.' '.$emp->supervisor->apellidos;
        }
        $lineas[] = 'Por favor coordinar la renovación.';

        return implode("\n", $lineas);
    }

    /** Enlace wa.me SIN número: WhatsApp abre y el usuario elige el contacto. */
    public function urlWhatsapp(): string
    {
        return 'https://wa.me/?text='.rawurlencode($this->mensajeWhatsapp());
    }

    // ---- Semáforo 🚦 ----

    /**
     * Estado del documento según su vigencia:
     * - sin_vigencia : el tipo no vence, o no tiene fecha de vencimiento
     * - vencido      : la fecha de vencimiento ya pasó
     * - por_vencer   : vence dentro de los "días de aviso previo" del tipo
     * - vigente      : todavía falta más que los días de aviso
     */
    public function getEstadoAttribute(): string
    {
        if (! $this->tipoDocumento?->requiere_vigencia || ! $this->fecha_vencimiento) {
            return self::SIN_VIGENCIA;
        }

        $hoy = now()->startOfDay();
        $venc = $this->fecha_vencimiento->copy()->startOfDay();

        if ($venc->lt($hoy)) {
            return self::VENCIDO;
        }

        $aviso = $this->tipoDocumento->dias_aviso_previo ?? 30;

        return $hoy->diffInDays($venc) <= $aviso
            ? self::POR_VENCER
            : self::VIGENTE;
    }

    /** Días que faltan para vencer (negativo si ya venció). Null si no aplica. */
    public function getDiasParaVencerAttribute(): ?int
    {
        if (! $this->fecha_vencimiento) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->fecha_vencimiento->copy()->startOfDay(), false);
    }

    // ---- Historial / documento "actual" ----

    /**
     * Documento ACTUAL de cada requisito (empleado + tipo): el de vencimiento
     * más reciente. Los demás quedan como historial (trazabilidad), pero no
     * "alarman" en el semáforo.
     */
    public static function actuales(): \Illuminate\Support\Collection
    {
        return static::with(['empleado', 'tipoDocumento'])
            ->get()
            ->groupBy(fn (self $d) => $d->empleado_id.'-'.$d->tipo_documento_id)
            ->map(fn ($grupo) => $grupo
                ->sortByDesc(fn (self $d) => [$d->fecha_vencimiento?->timestamp ?? 0, $d->id])
                ->first())
            ->values();
    }

    /** Conteo del semáforo considerando SOLO los documentos actuales. */
    public static function resumenSemaforo(): array
    {
        $actuales = static::actuales();

        return [
            'vigente' => $actuales->where('estado', self::VIGENTE)->count(),
            'por_vencer' => $actuales->where('estado', self::POR_VENCER)->count(),
            'vencido' => $actuales->where('estado', self::VENCIDO)->count(),
        ];
    }
}
