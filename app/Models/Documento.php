<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
