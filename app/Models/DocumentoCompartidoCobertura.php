<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoCompartidoCobertura extends Model
{
    protected $table = 'documento_compartido_cobertura';

    public $timestamps = false;

    protected $fillable = [
        'documento_compartido_id', 'tipo_documento_id', 'aseguradora', 'numero_poliza',
    ];

    public function documentoCompartido(): BelongsTo
    {
        return $this->belongsTo(DocumentoCompartido::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }
}
