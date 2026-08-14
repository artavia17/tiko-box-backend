<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Canton;
use App\Models\District;
use App\Models\ShippingAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/** Direcciones de entrega del cliente. */
class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->shippingAddresses()
            ->with(['province', 'canton', 'district'])
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $addresses->map($this->present(...))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $address = DB::transaction(function () use ($request, $data) {
            $isFirst = ! $request->user()->shippingAddresses()->exists();
            $makeDefault = $data['is_default'] ?? false;

            if ($makeDefault || $isFirst) {
                $request->user()->shippingAddresses()->update(['is_default' => false]);
            }

            return ShippingAddress::create([
                ...$data,
                'user_id' => $request->user()->id,
                'is_default' => $makeDefault || $isFirst,
            ]);
        });

        return response()->json([
            'data' => $this->present($address->load(['province', 'canton', 'district'])),
        ], 201);
    }

    public function update(Request $request, ShippingAddress $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);

        $data = $this->validated($request);

        DB::transaction(function () use ($request, $address, $data) {
            if ($data['is_default'] ?? false) {
                $request->user()->shippingAddresses()->update(['is_default' => false]);
            }

            $address->update([
                ...$data,
                // Nunca dejamos al cliente sin dirección preferida.
                'is_default' => ($data['is_default'] ?? false) || $address->is_default,
            ]);
        });

        return response()->json([
            'data' => $this->present($address->fresh()->load(['province', 'canton', 'district'])),
        ]);
    }

    public function makeDefault(Request $request, ShippingAddress $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);

        DB::transaction(function () use ($request, $address) {
            $request->user()->shippingAddresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return response()->json(['message' => 'Dirección preferida actualizada.']);
    }

    public function destroy(Request $request, ShippingAddress $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);

        if ($request->user()->shippingAddresses()->count() === 1) {
            throw ValidationException::withMessages([
                'address' => 'Necesitás al menos una dirección de entrega.',
            ]);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        // Si borró la preferida, la más antigua toma su lugar.
        if ($wasDefault) {
            $request->user()->shippingAddresses()->oldest('id')->first()?->update([
                'is_default' => true,
            ]);
        }

        return response()->json(['message' => 'Dirección eliminada.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validator = validator($request->all(), [
            'label' => ['required', 'string', 'max:60'],
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')],
            'canton_id' => ['required', 'integer', Rule::exists('cantons', 'id')],
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')],
            'exact_address' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_default' => ['boolean'],
        ]);

        $validator->after(function (Validator $validator) use ($request) {
            $canton = Canton::find($request->input('canton_id'));

            if ($canton && $canton->province_id !== (int) $request->input('province_id')) {
                $validator->errors()->add('canton_id', 'El cantón no pertenece a la provincia seleccionada.');
            }

            $district = District::find($request->input('district_id'));

            if ($district && $district->canton_id !== (int) $request->input('canton_id')) {
                $validator->errors()->add('district_id', 'El distrito no pertenece al cantón seleccionado.');
            }
        });

        return $validator->validate();
    }

    private function authorizeAddress(Request $request, ShippingAddress $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }

    /** @return array<string, mixed> */
    private function present(ShippingAddress $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'province_id' => $address->province_id,
            'canton_id' => $address->canton_id,
            'district_id' => $address->district_id,
            'province' => $address->province?->name,
            'canton' => $address->canton?->name,
            'district' => $address->district?->name,
            'exact_address' => $address->exact_address,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
            'is_default' => $address->is_default,
        ];
    }
}
