<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu código para recuperar la contraseña: {$this->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.password-reset-code',
            with: [
                'firstName' => $this->user->first_name,
                'code' => $this->code,
                'minutes' => (int) config('tikabox.verification.ttl_minutes'),
            ],
        );
    }
}
