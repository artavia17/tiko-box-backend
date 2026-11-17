<?php

namespace App\Services;

use App\Mail\SupportClosedMail;
use App\Mail\SupportMessageMail;
use App\Mail\SupportReplyMail;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos por correo del soporte.
 *
 * Nada de lo que pasa por acá puede tumbar la conversación: si el correo
 * falla, el mensaje ya quedó guardado y el problema se anota en el log.
 */
class SupportNotifier
{
    /** Según quién escribió, el aviso va a administración o al cliente. */
    public function messagePosted(SupportMessage $message, SupportTicket $ticket): void
    {
        $ticket->loadMissing('user');

        if ($message->user_id === $ticket->user_id) {
            $this->toAdmins($ticket, $message);

            return;
        }

        $this->send(
            $ticket->user?->email,
            fn () => new SupportReplyMail($ticket, $message),
            'la respuesta de soporte',
            ['ticket_id' => $ticket->id],
        );
    }

    /** Al cerrarlo se avisa al cliente, salvo que lo haya cerrado él mismo. */
    public function ticketClosed(SupportTicket $ticket, User $who): void
    {
        if ($who->id === $ticket->user_id) {
            return;
        }

        $this->send(
            $ticket->loadMissing('user')->user?->email,
            fn () => new SupportClosedMail($ticket),
            'el cierre de un ticket',
            ['ticket_id' => $ticket->id],
        );
    }

    /**
     * Un correo por administrador, no uno con todos en copia: así nadie ve
     * la dirección de los demás y una dirección mala no deja sin aviso al
     * resto.
     */
    private function toAdmins(SupportTicket $ticket, SupportMessage $message): void
    {
        $recipients = User::admins()->whereNotNull('email')->pluck('email');

        if ($recipients->isEmpty()) {
            Log::warning('No hay administradores a quién avisarle de un mensaje de soporte.', [
                'ticket_id' => $ticket->id,
            ]);

            return;
        }

        foreach ($recipients as $email) {
            $this->send(
                $email,
                fn () => new SupportMessageMail($ticket, $message),
                'un mensaje de soporte',
                ['ticket_id' => $ticket->id],
            );
        }
    }

    /**
     * @param  callable(): Mailable  $mailable
     * @param  array<string, mixed>  $context
     */
    private function send(?string $to, callable $mailable, string $about, array $context): void
    {
        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send($mailable());
        } catch (\Throwable $exception) {
            Log::warning("No se pudo avisar de {$about}.", [
                ...$context,
                'to' => $to,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
