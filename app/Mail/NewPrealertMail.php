<?php

namespace App\Mail;

use App\Models\Prealert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso a administración de que viene un paquete en camino. */
class NewPrealertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Prealert $prealert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nueva prealerta · {$this->prealert->tracking_number}");
    }

    public function content(): Content
    {
        $customer = $this->prealert->user;

        return new Content(
            view: 'mail.admin-notice',
            with: [
                'eyebrow' => 'Prealerta',
                'heading' => $this->prealert->tracking_number,
                'intro' => $customer
                    ? "{$customer->fullName()} avisó que este paquete viene en camino al almacén."
                    : 'Avisaron que este paquete viene en camino al almacén.',
                'rows' => array_filter([
                    'Cliente' => $customer?->fullName(),
                    'Casillero' => $customer?->locker_code,
                    'Teléfono' => $customer?->phone,
                    'Origen' => $this->prealert->origin,
                    'Llegada estimada' => $this->prealert->expected_arrival?->format('d/m/Y'),
                    'Factura' => $this->prealert->invoice_path ? 'Adjunta' : 'Sin adjuntar',
                ]),
                'actionUrl' => rtrim((string) config('app.frontend_url'), '/').'/dashboard/almacen/prealertas',
                'actionLabel' => 'Ver las prealertas',
            ],
        );
    }
}
