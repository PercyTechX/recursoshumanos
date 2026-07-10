<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvisoDocumento extends Model
{
    protected $table = 'avisos_documento';

    protected $fillable = [
        'documento_id', 'empleado_id', 'supervisor_id', 'canal', 'destino',
        'estado_documento', 'dias', 'enviado_por',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'supervisor_id');
    }
}
