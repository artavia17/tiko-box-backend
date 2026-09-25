<?php

namespace App\Services;

use App\Mail\NewCustomerMail;
use App\Mail\NewPrealertMail;
use App\Models\Prealert;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos internos a administración.
 *
 * Nada de lo que pasa por acá puede tumbar la operación del cliente: si el
 * correo falla, el registro o la prealerta ya quedaron guardados igual y el
 * problema se anota en el log.
 */
class AdminNotifier
{
    /** Alguien abrió su casillero desde el sitio. */
    public function customerRegistered(User $customer): void
    {
        $this->fanOut(
            fn () => new NewCustomerMail($customer),
            'el alta de un cliente',
            ['user_id' => $customer->id],
        );
    }

    /** Un cliente avisó que viene un paquete en camino. */
    public function prealertCreated(Prealert $prealert): void
    {
        $this->fanOut(
            fn () => new NewPrealertMail($prealert->loadMissing('user')),
            'una prealerta',
            ['prealert_id' => $prealert->id],
        );
    }

    /**
     * Un correo por administrador, no uno con todos en copia: así nadie ve
     * la dirección de los demás y una dirección mala no deja sin aviso al
     * resto.
     *
     * @param  callable(): Mailable  $mailable
     * @param  array<string, mixed>  $context
     */
    private function fanOut(callable $mailable, string $about, array $context): void
    {
        $recipients = User::admins()->whereNotNull('email')->pluck('email');

        if ($recipients->isEmpty()) {
            Log::warning("No hay administradores a quién avisarle de {$about}.", $context);

            return;
        }

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send($mailable());
            } catch (\Throwable $exception) {
                Log::warning("No se pudo avisar de {$about}.", [
                    ...$context,
                    'admin' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
