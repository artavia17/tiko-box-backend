<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso a administración de que alguien abrió su casillero. */
class NewCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $customer) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nuevo cliente: {$this->customer->fullName()}");
    }

    public function content(): Content
    {
        $address = $this->customer->defaultShippingAddress()
            ->with(['province', 'canton', 'district'])
            ->first();

        return new Content(
            view: 'mail.admin-notice',
            with: [
                'eyebrow' => 'Registro',
                'heading' => $this->customer->fullName(),
                'intro' => 'Abrió su casillero desde el sitio. Todavía tiene que confirmar el correo para poder entrar.',
                'rows' => array_filter([
                    'Correo' => $this->customer->email,
                    'Teléfono' => $this->customer->phone,
                    'Cédula' => $this->customer->identification,
                    'Casillero' => $this->customer->locker_code,
                    'Entrega' => $address ? trim(implode(', ', array_filter([
                        $address->district?->name,
                        $address->canton?->name,
                        $address->province?->name,
                    ]))) : null,
                ]),
                'actionUrl' => rtrim((string) config('app.frontend_url'), '/').'/dashboard/admin/usuarios',
                'actionLabel' => 'Ver la cuenta',
            ],
        );
    }
}
