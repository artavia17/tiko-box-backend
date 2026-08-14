<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Verificación de correo con un código de 4 dígitos.
 *
 * El código se guarda hasheado, vence a los N minutos y se limita la cantidad
 * de intentos para que no se pueda adivinar por fuerza bruta.
 */
class EmailVerificationService
{
    public function send(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerificationCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes((int) config('tikabox.verification.ttl_minutes')),
            ],
        );

        Mail::to($user->email)->send(new VerificationCodeMail($user, $code));
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function verify(User $user, string $code): array
    {
        $record = EmailVerificationCode::where('user_id', $user->id)->first();

        if (! $record) {
            return ['ok' => false, 'error' => 'Pedí un código nuevo para continuar.'];
        }

        if ($record->isExpired()) {
            return ['ok' => false, 'error' => 'El código venció. Pedí uno nuevo.'];
        }

        $maxAttempts = (int) config('tikabox.verification.max_attempts');

        if ($record->attempts >= $maxAttempts) {
            return ['ok' => false, 'error' => 'Demasiados intentos. Pedí un código nuevo.'];
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return ['ok' => false, 'error' => 'El código no es correcto.'];
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $record->delete();

        return ['ok' => true];
    }
}
