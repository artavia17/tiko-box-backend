<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Services\LockerCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Registro: crea la cuenta, asigna el casillero y guarda la dirección de envío.
     */
    public function register(RegisterRequest $request, LockerCodeGenerator $lockers): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $lockers) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'second_last_name' => $data['second_last_name'] ?? null,
                'identification' => $data['identification'],
                'phone' => $data['phone'],
                'locker_code' => $lockers->next(),
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
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_default' => true,
            ]);

            return $user;
        });

        return response()->json([
            'token' => $user->createToken('tikabox')->plainTextToken,
            'user' => new UserResource($this->withRelations($user)),
        ], 201);
    }

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

        return response()->json([
            'token' => $user->createToken('tikabox')->plainTextToken,
            'user' => new UserResource($this->withRelations($user)),
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

    private function withRelations(User $user): User
    {
        return $user->load([
            'defaultShippingAddress.province',
            'defaultShippingAddress.canton',
            'defaultShippingAddress.district',
        ]);
    }
}
