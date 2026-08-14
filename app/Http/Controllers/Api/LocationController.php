<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Canton;
use App\Models\District;
use App\Models\Province;
use Illuminate\Http\JsonResponse;

/**
 * Provincias, cantones y distritos de Costa Rica para los selects del registro.
 */
class LocationController extends Controller
{
    public function provinces(): JsonResponse
    {
        return response()->json([
            'data' => Province::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function cantons(Province $province): JsonResponse
    {
        return response()->json([
            'data' => $province->cantons()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function districts(Canton $canton): JsonResponse
    {
        return response()->json([
            'data' => $canton->districts()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }
}
