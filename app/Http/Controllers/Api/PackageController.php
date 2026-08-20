<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageEvent;
use App\Services\PackageTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Los paquetes vistos por su dueño. */
class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $packages = $request->user()
            ->packages()
            ->with(['events', 'photos'])
            ->when($request->query('search'), function ($query, string $search) {
                $query->where('tracking_number', 'like', "%{$search}%");
            })
            ->when($request->query('status'), function ($query, string $status) {
                $query->where('status', $status);
            })
            ->latest('received_at')
            ->paginate((int) $request->query('per_page', 10));

        return response()->json([
            'data' => collect($packages->items())->map($this->present(...)),
            'meta' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'total' => $packages->total(),
                'per_page' => $packages->perPage(),
                // Para los totales de la cabecera sin pedir otra página.
                'pending_total' => (float) $request->user()->packages()
                    ->where('status', '!=', 'entregado')
                    ->sum('total'),
            ],
        ]);
    }

    public function show(Request $request, Package $package): JsonResponse
    {
        abort_unless($package->user_id === $request->user()->id, 404);

        return response()->json(['data' => $this->present($package->load(['events', 'photos']))]);
    }

    /**
     * Lo que se le rebajó al cliente, si se le rebajó algo.
     *
     * @return array<string, mixed>
     */
    private function discount(Package $package): array
    {
        $listed = (float) $package->original_total;

        if (! $package->original_total || $listed <= (float) $package->total) {
            return [];
        }

        $saved = $listed - (float) $package->total;

        return [
            'original_total' => round($listed, 2),
            'discount' => round($saved, 2),
            'discount_percent' => (int) round($saved / $listed * 100),
        ];
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
            'weight_breakdown' => $package->weight_breakdown,
            'price_per_pound' => $package->price_per_pound,
            'total' => $package->total,
            // Solo se le cuenta al cliente cuando salió ganando: si el monto
            // subió, el dato ni siquiera sale del servidor.
            ...$this->discount($package),
            'status' => $package->status,
            'status_description' => PackageTracker::DESCRIPTIONS[$package->status] ?? null,
            'received_at' => $package->received_at?->toDateTimeString(),
            'delivered_at' => $package->delivered_at?->toDateTimeString(),
            'delivered_to_name' => $package->delivered_to_name,
            'has_signature' => (bool) $package->signature_path,
            'photos' => $package->photos->map(fn ($photo) => ['id' => $photo->id]),
            'events' => $package->events->map(fn (PackageEvent $event) => [
                'id' => $event->id,
                'status' => $event->status,
                'description' => PackageTracker::DESCRIPTIONS[$event->status] ?? null,
                'note' => $event->note,
                'at' => $event->created_at->toDateTimeString(),
            ]),
        ];
    }
}
