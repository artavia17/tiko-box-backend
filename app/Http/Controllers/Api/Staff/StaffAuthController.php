<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sesión de la app interna. Los tokens llevan la habilidad "staff", que es
 * lo que exige el middleware: un token de la app de clientes no sirve acá.
 */
class StaffAuthController extends Controller
{
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

        if (! $user->isStaff()) {
            throw ValidationException::withMessages([
                'email' => 'Esta cuenta no tiene acceso a la app interna.',
            ]);
        }

        return response()->json([
            'token' => $user->createToken('tikabox-staff', ['staff'])->plainTextToken,
            'user' => $this->present($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    /** Alta de personal: solo un administrador puede crear cuentas internas. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['empleado', 'admin'])],
        ]);

        $user = User::create([
            ...$data,
            'name' => "{$data['first_name']} {$data['last_name']}",
            // El personal no pasa por la verificación de la app de clientes.
            'email_verified_at' => now(),
        ]);

        return response()->json(['data' => $this->present($user)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $staff = User::whereIn('role', ['empleado', 'admin'])
            ->orderBy('first_name')
            ->get();

        return response()->json(['data' => $staff->map($this->present(...))]);
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->fullName(),
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
