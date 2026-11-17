<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso al cliente de que su ticket se cerró. */
class SupportClosedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportTicket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Cerramos tu ticket · {$this->ticket->subject}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.customer-notice',
            with: [
                'eyebrow' => "Ticket #{$this->ticket->id}",
                'heading' => 'Tu ticket quedó cerrado',
                'intro' => 'Si el tema sigue sin resolverse, abrí uno nuevo y lo retomamos. '
                    .'El historial de este queda guardado en tu panel.',
                'quote' => null,
                'actionUrl' => rtrim((string) config('app.frontend_url'), '/').'/dashboard/soporte',
                'actionLabel' => 'Ver mis tickets',
            ],
        );
    }
}
