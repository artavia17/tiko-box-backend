<?php

namespace App\Mail;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso a administración de que un cliente escribió a soporte. */
class SupportMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public SupportMessage $message,
    ) {}

    public function envelope(): Envelope
    {
        $prefijo = $this->ticket->messages()->count() > 1 ? 'Respuesta' : 'Nuevo ticket';

        return new Envelope(subject: "{$prefijo} #{$this->ticket->id} · {$this->ticket->subject}");
    }

    public function content(): Content
    {
        $customer = $this->ticket->user;
        $adjuntos = $this->message->attachments->count();

        return new Content(
            view: 'mail.admin-notice',
            with: [
                'eyebrow' => 'Soporte · '.(SupportTicket::CATEGORIES[$this->ticket->category] ?? 'Consulta'),
                'heading' => $this->ticket->subject,
                'intro' => $this->message->body
                    // Sin el texto completo: el correo es un aviso, la
                    // conversación vive en la plataforma.
                    ?: 'El cliente mandó un archivo sin texto.',
                'rows' => array_filter([
                    'Ticket' => "#{$this->ticket->id}",
                    'Cliente' => $customer?->fullName(),
                    'Casillero' => $customer?->locker_code,
                    'Teléfono' => $customer?->phone,
                    'Adjuntos' => $adjuntos > 0 ? "{$adjuntos}" : null,
                ]),
                'actionUrl' => rtrim((string) config('app.frontend_url'), '/')
                    ."/dashboard/admin/soporte?ticket={$this->ticket->id}",
                'actionLabel' => 'Abrir el ticket',
            ],
        );
    }
}
