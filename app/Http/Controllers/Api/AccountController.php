<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuthorizedPerson;
use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ajustes de la cuenta: información personal, personas autorizadas y
 * cambio de contraseña.
 */
class AccountController extends Controller
{
    public function __construct(
        private readonly EmailVerificationService $verification,
    ) {}

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'second_last_name' => ['nullable', 'string', 'max:100'],
            'phone' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{4}$/',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'authorized_persons' => ['array', 'max:2'],
            'authorized_persons.*.name' => ['required', 'string', 'max:150'],
            'authorized_persons.*.identification' => ['required', 'string', 'max:30'],
            'authorized_persons.*.phone' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
        ], [
            'phone.regex' => 'El teléfono debe tener el formato 8888-8888.',
            'authorized_persons.*.phone.regex' => 'El teléfono debe tener el formato 8888-8888.',
        ]);

        $emailChanged = $data['email'] !== $user->email;

        DB::transaction(function () use ($user, $data, $emailChanged) {
            $user->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'second_last_name' => $data['second_last_name'] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'],
                'name' => trim(implode(' ', array_filter([
                    $data['first_name'],
                    $data['last_name'],
                    $data['second_last_name'] ?? null,
                ]))),
            ]);

            // Cambiar de correo obliga a confirmarlo otra vez.
            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();

            $user->authorizedPersons()->delete();

            foreach ($data['authorized_persons'] ?? [] as $person) {
                AuthorizedPerson::create([
                    'user_id' => $user->id,
                    'name' => $person['name'],
                    'identification' => $person['identification'],
                    'phone' => $person['phone'],
                ]);
            }
        });

        if ($emailChanged) {
            $this->verification->send($user);

            // El token actual deja de servir: hay que confirmar el correo nuevo.
            $user->tokens()->delete();

            return response()->json([
                'requires_verification' => true,
                'email' => $user->email,
                'message' => 'Te enviamos un código al correo nuevo para confirmarlo.',
            ]);
        }

        return response()->json([
            'data' => new UserResource($user->fresh()->load([
                'authorizedPersons',
                'defaultShippingAddress.province',
                'defaultShippingAddress.canton',
                'defaultShippingAddress.district',
            ])),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        $user->update(['password' => $data['password']]);

        // Cerramos las demás sesiones y dejamos viva la actual.
        $current = $request->user()->currentAccessToken();
        $user->tokens()->whereKeyNot($current->getKey())->delete();

        return response()->json(['message' => 'Contraseña actualizada.']);
    }
}
