<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Prealert;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Registro y seguimiento de paquetes desde el almacén. */
class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $packages = Package::with('user')
            ->when($request->query('search'), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('tracking_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('locker_code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => collect($packages->items())->map($this->present(...)),
            'meta' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'total' => $packages->total(),
            ],
        ]);
    }

    /**
     * Registra un paquete recibido en Miami.
     *
     * El casillero es el mismo para toda la operación, así que no sirve para
     * saber de quién es la caja: el cliente se elige por id desde el buscador
     * de la app.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'tracking_number' => ['required', 'string', 'max:60'],
            'weight_lb' => ['required', 'numeric', 'min:0.1', 'max:500'],
            'courier' => ['nullable', 'string', 'max:60'],
            'store' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = User::where('role', 'cliente')->find($data['customer_id']);

        if (! $customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'No encontramos ese cliente.',
            ]);
        }

        $tracking = strtoupper(trim($data['tracking_number']));

        if (Package::where('user_id', $customer->id)->where('tracking_number', $tracking)->exists()) {
            throw ValidationException::withMessages([
                'tracking_number' => 'Ese paquete ya está registrado para este cliente.',
            ]);
        }

        $pricePerPound = (float) config('tikabox.price_per_pound');

        $package = DB::transaction(function () use ($customer, $data, $tracking, $pricePerPound, $request) {
            // Si el cliente lo había prealertado, se enlaza y se marca recibida.
            $prealert = Prealert::where('user_id', $customer->id)
                ->where('tracking_number', $tracking)
                ->first();

            $prealert?->update(['status' => 'recibido']);

            return Package::create([
                'user_id' => $customer->id,
                'registered_by' => $request->user()->id,
                'prealert_id' => $prealert?->id,
                'tracking_number' => $tracking,
                'courier' => $data['courier'] ?? $prealert?->courier,
                'store' => $data['store'] ?? null,
                'description' => $data['description'] ?? null,
                'weight_lb' => $data['weight_lb'],
                'price_per_pound' => $pricePerPound,
                'total' => round($data['weight_lb'] * $pricePerPound, 2),
                'status' => 'recibido',
                'received_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->present($package->fresh()->load('user')),
        ], 201);
    }

    public function updateStatus(Request $request, Package $package): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['recibido', 'en_transito', 'listo', 'entregado'])],
        ]);

        $package->update([
            'status' => $data['status'],
            'delivered_at' => $data['status'] === 'entregado' ? now() : null,
        ]);

        return response()->json(['data' => $this->present($package->fresh()->load('user'))]);
    }

    public function destroy(Package $package): JsonResponse
    {
        if ($package->status === 'entregado') {
            throw ValidationException::withMessages([
                'status' => 'Un paquete entregado no se puede eliminar.',
            ]);
        }

        $package->delete();

        return response()->json(['message' => 'Paquete eliminado.']);
    }

    /** Resumen para la pantalla de inicio de la app. */
    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => [
                'recibidos_hoy' => Package::whereDate('received_at', today())->count(),
                'en_transito' => Package::where('status', 'en_transito')->count(),
                'listos' => Package::where('status', 'listo')->count(),
                'entregados_mes' => Package::where('status', 'entregado')
                    ->whereMonth('delivered_at', now()->month)
                    ->whereYear('delivered_at', now()->year)
                    ->count(),
                'clientes' => User::where('role', 'cliente')->count(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Package $package): array
    {
        return [
            'id' => $package->id,
            'tracking_number' => $package->tracking_number,
            'courier' => $package->courier,
            'store' => $package->store,
            'description' => $package->description,
            'weight_lb' => $package->weight_lb,
            'price_per_pound' => $package->price_per_pound,
            'total' => $package->total,
            'status' => $package->status,
            'received_at' => $package->received_at?->toDateTimeString(),
            'delivered_at' => $package->delivered_at?->toDateTimeString(),
            'customer' => [
                'id' => $package->user->id,
                'locker_code' => $package->user->locker_code,
                'full_name' => $package->user->fullName(),
                'phone' => $package->user->phone,
            ],
        ];
    }
}
