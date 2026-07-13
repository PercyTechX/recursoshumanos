<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAvance extends Model
{
    protected $table = 'ticket_avances';

    public $timestamps = false;

    protected $fillable = [
        'ticket_tecnico_id', 'estado', 'fecha_hora', 'latitud', 'longitud', 'precision_m',
        'dentro_geocerca', 'es_manual', 'registrado_por', 'motivo',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'dentro_geocerca' => 'boolean',
        'es_manual' => 'boolean',
    ];

    public function ticketTecnico(): BelongsTo
    {
        return $this->belongsTo(TicketTecnico::class);
    }
}
