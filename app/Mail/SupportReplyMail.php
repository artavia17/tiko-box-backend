<?php

namespace App\Mail;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso al cliente de que soporte le respondió. */
class SupportReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public SupportMessage $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Soporte respondió · {$this->ticket->subject}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.customer-notice',
            with: [
                'eyebrow' => "Ticket #{$this->ticket->id}",
                'heading' => 'Te respondimos',
                'intro' => 'Soporte contestó tu consulta. Podés seguir la conversación desde tu panel.',
                'quote' => $this->message->body
                    ?: 'Te mandamos un archivo. Abrí el ticket para verlo.',
                'actionUrl' => rtrim((string) config('app.frontend_url'), '/')
                    ."/dashboard/soporte?ticket={$this->ticket->id}",
                'actionLabel' => 'Ver la respuesta',
            ],
        );
    }
}
