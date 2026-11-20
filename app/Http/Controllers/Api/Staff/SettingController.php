<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ajustes del negocio.
 *
 * Los lee cualquiera del almacén —los usa para trabajar— pero solo
 * administración los cambia.
 */
class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Setting::values()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'whatsapp_location_message' => ['required', 'string', 'max:1000'],
        ], [
            'whatsapp_location_message.required' => 'El mensaje no puede quedar vacío.',
            'whatsapp_location_message.max' => 'El mensaje no puede pasar de 1000 caracteres.',
        ]);

        foreach ($data as $key => $value) {
            // Solo lo que el código conoce: un ajuste inventado desde fuera
            // quedaría guardado sin que nada lo lea nunca.
            if (! array_key_exists($key, Setting::DEFAULTS)) {
                continue;
            }

            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return response()->json(['data' => Setting::values()]);
    }
}
