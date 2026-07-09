<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntregaEpp extends Model
{
    protected $table = 'entregas_epp';

    protected $fillable = [
        'empleado_id', 'tipo_epp_id', 'cantidad', 'talla', 'fecha',
        'firma_path', 'entregado_por', 'observacion',
    ];

    protected $casts = ['fecha' => 'date'];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function tipoEpp(): BelongsTo
    {
        return $this->belongsTo(TipoEpp::class);
    }

    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }
}
