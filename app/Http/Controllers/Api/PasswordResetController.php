<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Recuperación de contraseña con un código de 4 dígitos, igual que la
 * verificación de correo. Se apoya en la tabla password_reset_tokens.
 */
class PasswordResetController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($code), 'created_at' => now()],
            );

            Mail::to($user->email)->send(new PasswordResetCodeMail($user, $code));
        }

        // Respuesta igual exista o no la cuenta, para no filtrar correos.
        return response()->json([
            'message' => 'Si esa cuenta existe, te enviamos un código para cambiar la contraseña.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'code' => 'Pedí un código nuevo para continuar.',
            ]);
        }

        $minutes = (int) config('tikabox.verification.ttl_minutes');

        if (now()->diffInMinutes($record->created_at) > $minutes) {
            throw ValidationException::withMessages([
                'code' => 'El código venció. Pedí uno nuevo.',
            ]);
        }

        if (! Hash::check($data['code'], $record->token)) {
            throw ValidationException::withMessages([
                'code' => 'El código no es correcto.',
            ]);
        }

        $user = User::where('email', $data['email'])->firstOrFail();

        DB::transaction(function () use ($user, $data) {
            $user->update([
                'password' => $data['password'],
                // Quien recibe el código en su correo lo está confirmando.
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            // Cambiar la contraseña cierra todas las sesiones abiertas.
            $user->tokens()->delete();

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });

        return response()->json([
            'token' => $user->createToken('tikabox')->plainTextToken,
            'user' => new UserResource($user->load([
                'authorizedPersons',
                'defaultShippingAddress.province',
                'defaultShippingAddress.canton',
                'defaultShippingAddress.district',
            ])),
        ]);
    }
}
