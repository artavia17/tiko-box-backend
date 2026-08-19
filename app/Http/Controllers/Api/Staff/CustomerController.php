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

        // El empleado busca al dueño de una caja concreta; recorrer el
        // directorio entero de clientes es cosa de administración.
        abort_if($search === '' && ! $request->user()->isAdmin(), 403,
            'Buscá al cliente por nombre, cédula o correo.');

        $customers = User::where('role', 'cliente')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    foreach (['locker_code', 'name', 'identification', 'email', 'phone'] as $field) {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                });
            })
            ->withCount('packages')
            ->orderBy('name')
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

    /** Ficha completa: es lo que se consulta antes de entregar un paquete. */
    public function show(Request $request, User $customer): JsonResponse
    {
        abort_unless($customer->role === 'cliente', 404);

        $customer->load([
            'shippingAddresses.province',
            'shippingAddresses.canton',
            'shippingAddresses.district',
            'authorizedPersons',
        ]);

        return response()->json([
            'data' => [
                ...$this->present($customer->loadCount('packages')),
                'identification_type' => $customer->identification_type,
                'email_verified' => $customer->hasVerifiedEmail(),
                'registered_at' => $customer->created_at->toDateString(),
                // Todas, con la principal marcada: el cliente puede tener
                // varias y hay que saber a cuál va el paquete.
                'addresses' => $customer->shippingAddresses
                    ->sortByDesc('is_default')
                    ->values()
                    ->map(fn ($address) => [
                        'id' => $address->id,
                        'label' => $address->label,
                        'province' => $address->province?->name,
                        'canton' => $address->canton?->name,
                        'district' => $address->district?->name,
                        'exact_address' => $address->exact_address,
                        'latitude' => $address->latitude,
                        'longitude' => $address->longitude,
                        'is_default' => (bool) $address->is_default,
                    ]),
                'authorized_persons' => $customer->authorizedPersons->map(fn ($person) => [
                    'name' => $person->name,
                    'identification_type' => $person->identification_type,
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
