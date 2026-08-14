<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Comprueba que cédula, teléfono o correo no estén tomados, para avisarle al
 * usuario en el paso del formulario donde los escribió y no al final.
 */
class AvailabilityController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identification' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
        ]);

        $messages = [
            'identification' => 'Esa identificación ya está registrada.',
            'phone' => 'Ese teléfono ya está registrado.',
            'email' => 'Ese correo ya está registrado.',
        ];

        $errors = [];

        foreach ($messages as $field => $message) {
            if (! empty($data[$field]) && User::where($field, $data[$field])->exists()) {
                $errors[$field] = [$message];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return response()->json(['available' => true]);
    }
}
