<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Datos del casillero del cliente: lo que debe copiar en el checkout de las
 * tiendas de Estados Unidos.
 */
class LockerController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $warehouse = config('tikabox.warehouse');

        return response()->json([
            'data' => [
                'code' => $user->locker_code,
                'name' => $user->locker_code.' '.$user->first_name,
                'last_name' => trim($user->last_name.' '.($user->second_last_name ?? '')),
                'address' => $warehouse['address'],
                'address_line_2' => $user->locker_code,
                'city' => $warehouse['city'],
                'state' => $warehouse['state'],
                'zip' => $warehouse['zip'],
                'country' => $warehouse['country'],
                'phone' => $warehouse['phone'],
                'price_per_pound' => config('tikabox.price_per_pound'),
            ],
        ]);
    }
}
