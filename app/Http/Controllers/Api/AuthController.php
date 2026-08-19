<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly EmailVerificationService $verification,
    ) {}

    /**
     * Registro: crea la cuenta, guarda la dirección de envío y manda el
     * código de verificación. No entrega token todavía: la cuenta no sirve
     * hasta confirmar el correo.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'second_last_name' => $data['second_last_name'] ?? null,
                'identification_type' => $data['identification_type'],
                'identification' => $data['identification'],
                'phone' => $data['phone'],
                'locker_code' => config('tikabox.locker.code'),
                'name' => trim(implode(' ', array_filter([
                    $data['first_name'],
                    $data['last_name'],
                    $data['second_last_name'] ?? null,
                ]))),
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            ShippingAddress::create([
                'user_id' => $user->id,
                'province_id' => $data['province_id'],
                'canton_id' => $data['canton_id'],
                'district_id' => $data['district_id'],
                'exact_address' => $data['exact_address'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'is_default' => true,
            ]);

            return $user;
        });

        $this->verification->send($user);

        return response()->json([
            'requires_verification' => true,
            'email' => $user->email,
            'message' => 'Te enviamos un código de 6 dígitos a tu correo.',
        ], 201);
    }

    /**
     * Login. Si el correo no está verificado, reenvía el código y le pide al
     * frontend que muestre la pantalla de verificación.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $this->verification->send($user);

            return response()->json([
                'requires_verification' => true,
                'email' => $user->email,
                'message' => 'Te reenviamos el código para confirmar tu correo.',
            ], 403);
        }

        return $this->tokenResponse($user);
    }

    /**
     * Confirma el correo con el código de 6 dígitos y entrega el token.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'No encontramos una cuenta con ese correo.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->tokenResponse($user);
        }

        $result = $this->verification->verify($user, $data['code']);

        if (! $result['ok']) {
            throw ValidationException::withMessages(['code' => $result['error']]);
        }

        return $this->tokenResponse($user);
    }

    public function resendCode(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();

        // No revelamos si el correo existe o no.
        if ($user && ! $user->hasVerifiedEmail()) {
            $this->verification->send($user);
        }

        return response()->json([
            'message' => 'Si la cuenta existe y está sin confirmar, te enviamos un código nuevo.',
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($this->withRelations($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    private function tokenResponse(User $user): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken('tikabox')->plainTextToken,
            'user' => new UserResource($this->withRelations($user)),
        ]);
    }

    private function withRelations(User $user): User
    {
        return $user->load([
            'authorizedPersons',
            'defaultShippingAddress.province',
            'defaultShippingAddress.canton',
            'defaultShippingAddress.district',
        ]);
    }
}
