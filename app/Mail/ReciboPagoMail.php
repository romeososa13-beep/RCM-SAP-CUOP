<?php

namespace App\Mail;

use App\Models\Ajuste;
use App\Models\Pago;
use App\Services\Cobranza\ReciboPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Recibo de pago por correo, con el PDF adjunto (media carta). Se envía en segundo plano. */
class ReciboPagoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Pago $pago) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Recibo de pago folio {$this->pago->folio_pago} · ".Ajuste::institucion()['institucion_nombre']);
    }

    public function content(): Content
    {
        $this->pago->loadMissing('adeudo.alumno');

        return new Content(markdown: 'mail.recibo-pago', with: [
            'pago' => $this->pago,
            'alumno' => $this->pago->adeudo->alumno,
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(ReciboPdf::class)->generar($this->pago, 'media-carta')->output(),
                "recibo-{$this->pago->folio_pago}.pdf")->withMime('application/pdf'),
        ];
    }
}
