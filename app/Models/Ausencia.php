<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Ausencia extends Model
{
    protected $table = 'ausencias';

    protected $fillable = [
        'empleado_id', 'tipo', 'con_goce', 'fecha_inicio', 'fecha_fin', 'dias',
        'documento_ref', 'motivo', 'archivo_path', 'archivo_nombre', 'created_by',
    ];

    protected $casts = [
        'con_goce' => 'boolean',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public const DESCANSO_MEDICO = 'descanso_medico';
    public const LICENCIA_CON_GOCE = 'licencia_con_goce';
    public const LICENCIA_SIN_GOCE = 'licencia_sin_goce';
    public const PERMISO = 'permiso';
    public const FALTA = 'falta';

    /** tipo => [label, con_goce por defecto] */
    public const TIPOS = [
        self::DESCANSO_MEDICO => ['Descanso médico (CITT)', true],
        self::LICENCIA_CON_GOCE => ['Licencia con goce', true],
        self::LICENCIA_SIN_GOCE => ['Licencia sin goce', false],
        self::PERMISO => ['Permiso', true],
        self::FALTA => ['Falta', false],
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo][0] ?? ucfirst(str_replace('_', ' ', $this->tipo));
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
