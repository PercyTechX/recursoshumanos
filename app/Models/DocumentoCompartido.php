<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoCompartido extends Model
{
    protected $table = 'documentos_compartidos';

    protected $fillable = [
        'fecha_emision', 'fecha_vencimiento', 'archivo_path', 'archivo_nombre', 'observacion',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    // Estados del semáforo (mismos que Documento)
    public const VIGENTE = 'vigente';
    public const POR_VENCER = 'por_vencer';
    public const VENCIDO = 'vencido';
    public const SIN_VIGENCIA = 'sin_vigencia';

    // ---- Relaciones ----
    public function coberturas(): HasMany
    {
        return $this->hasMany(DocumentoCompartidoCobertura::class);
    }

    public function empleados(): BelongsToMany
    {
        return $this->belongsToMany(Empleado::class, 'documento_compartido_empleado');
    }

    // ---- Semáforo 🚦 ----

    /**
     * Estado según la vigencia. El "aviso previo" es el mayor entre los tipos
     * que ampara (para no avisar tarde en la cobertura más exigente).
     */
    public function getEstadoAttribute(): string
    {
        if (! $this->fecha_vencimiento) {
            return self::SIN_VIGENCIA;
        }

        $hoy = now()->startOfDay();
        $venc = $this->fecha_vencimiento->copy()->startOfDay();

        if ($venc->lt($hoy)) {
            return self::VENCIDO;
        }

        $aviso = (int) $this->coberturas->max(fn (DocumentoCompartidoCobertura $c) => $c->tipoDocumento?->dias_aviso_previo ?? 30) ?: 30;

        return $hoy->diffInDays($venc) <= $aviso
            ? self::POR_VENCER
            : self::VIGENTE;
    }

    public function getDiasParaVencerAttribute(): ?int
    {
        if (! $this->fecha_vencimiento) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->fecha_vencimiento->copy()->startOfDay(), false);
    }

    /** Nombres de las coberturas amparadas, ej. "SCTR Salud, SCTR Pensión". */
    public function getCoberturasTextoAttribute(): string
    {
        return $this->coberturas
            ->map(fn (DocumentoCompartidoCobertura $c) => $c->tipoDocumento?->nombre)
            ->filter()
            ->implode(', ');
    }

    /**
     * Conteo del semáforo por requisito (empleado × cobertura): un SCTR de 26
     * personas con 2 coberturas cuenta como 52 requisitos.
     */
    public static function resumenSemaforo(): array
    {
        $docs = static::with(['coberturas.tipoDocumento'])->withCount(['empleados', 'coberturas'])->get();

        $resumen = ['vigente' => 0, 'por_vencer' => 0, 'vencido' => 0];
        foreach ($docs as $d) {
            if (! array_key_exists($d->estado, $resumen)) {
                continue;
            }
            $resumen[$d->estado] += $d->empleados_count * max(1, $d->coberturas_count);
        }

        return $resumen;
    }
}
