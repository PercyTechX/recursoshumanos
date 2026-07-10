<?php

namespace App\Mail;

use App\Models\Documento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AvisoVencimientoDocumento extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Documento $documento)
    {
    }

    public function envelope(): Envelope
    {
        $emp = $this->documento->empleado;
        $tipo = $this->documento->tipoDocumento?->nombre ?? 'Documento';
        $prefijo = $this->documento->estado === 'vencido' ? '[VENCIDO]' : '[POR VENCER]';

        return new Envelope(
            subject: "{$prefijo} {$tipo} — {$emp?->nombres} {$emp?->apellidos}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.aviso-vencimiento',
            with: [
                'empleado' => $this->documento->empleado,
                'tipo' => $this->documento->tipoDocumento?->nombre ?? 'Documento',
                'estado' => $this->documento->estado,
                'fechaVencimiento' => optional($this->documento->fecha_vencimiento)->format('d/m/Y'),
                'dias' => $this->documento->dias_para_vencer,
            ],
        );
    }
}
