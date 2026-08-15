<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consulta de clientes desde la app del almacén. */
class CustomerController extends Controller
{
    /**
     * Busca por código de casillero, nombre, cédula, correo o teléfono, que es
     * lo que el empleado tiene a mano cuando llega un paquete.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $customers = User::where('role', 'cliente')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    foreach (['locker_code', 'name', 'identification', 'email', 'phone'] as $field) {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                });
            })
            ->withCount('packages')
            ->orderBy('locker_code')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => collect($customers->items())->map($this->present(...)),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function show(Request $request, User $customer): JsonResponse
    {
        abort_unless($customer->role === 'cliente', 404);

        $customer->load([
            'defaultShippingAddress.province',
            'defaultShippingAddress.canton',
            'defaultShippingAddress.district',
            'authorizedPersons',
        ]);

        $address = $customer->defaultShippingAddress;

        return response()->json([
            'data' => [
                ...$this->present($customer),
                'identification_type' => $customer->identification_type,
                'email_verified' => $customer->hasVerifiedEmail(),
                'registered_at' => $customer->created_at->toDateString(),
                'address' => $address ? [
                    'province' => $address->province?->name,
                    'canton' => $address->canton?->name,
                    'district' => $address->district?->name,
                    'exact_address' => $address->exact_address,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude,
                ] : null,
                'authorized_persons' => $customer->authorizedPersons->map(fn ($person) => [
                    'name' => $person->name,
                    'identification' => $person->identification,
                    'phone' => $person->phone,
                ]),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(User $customer): array
    {
        return [
            'id' => $customer->id,
            'locker_code' => $customer->locker_code,
            'full_name' => $customer->fullName(),
            'identification' => $customer->identification,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'packages_count' => $customer->packages_count ?? 0,
        ];
    }
}
