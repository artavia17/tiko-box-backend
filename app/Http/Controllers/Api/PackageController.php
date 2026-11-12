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
            ->with(['events', 'photos', 'paymentReports'])
            ->when($request->query('search'), function ($query, string $search) {
                $query->where('tracking_number', 'like', "%{$search}%");
            })
            // Lo anulado vive aparte, como una papelera: no se cobra y no se
            // mueve, así que no tiene por qué mezclarse con lo que el cliente
            // está esperando.
            ->when(
                $request->query('status') === 'anulados',
                fn ($query) => $query->whereNotNull('voided_at'),
                fn ($query) => $query->whereNull('voided_at'),
            )
            // "activos" es lo que sigue en movimiento: cuenta lo mismo que la
            // tarjeta "En camino" del inicio.
            ->when($request->query('status') === 'activos',
                fn ($query) => $query->whereIn('status', PackageTracker::openStatuses()))
            ->when(
                in_array($request->query('status'), PackageTracker::statuses(), true),
                fn ($query) => $query->where('status', $request->query('status')),
            )
            // Por el punto del recorrido: primero lo que llegó a Miami y
            // último lo entregado, que ya no requiere nada del cliente.
            ->orderByRaw("CASE status
                WHEN 'recibido' THEN 1
                WHEN 'en_transito' THEN 2
                WHEN 'aduanas' THEN 3
                WHEN 'bodega' THEN 4
                WHEN 'en_ruta' THEN 5
                ELSE 6 END")
            ->latest('received_at')
            ->paginate((int) $request->query('per_page', 10));

        return response()->json([
            'data' => collect($packages->items())->map($this->present(...)),
            'meta' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'total' => $packages->total(),
                'per_page' => $packages->perPage(),
                // Sobre todos los paquetes, no solo los de esta página: lo que
                // se debe no depende de cuántos quepan en pantalla.
                // Una venta anulada no se le cobra a nadie.
                'pending_total' => (float) $request->user()->packages()
                    ->where('status', '!=', 'entregado')
                    ->whereNull('voided_at')
                    ->sum('total'),
                // Cuántos hay en la papelera, para el chip de la pantalla.
                'voided_total' => $request->user()->packages()
                    ->whereNotNull('voided_at')
                    ->count(),
                'pending_count' => $request->user()->packages()
                    ->where('status', '!=', 'entregado')
                    ->whereNull('voided_at')
                    ->count(),
            ],
        ]);
    }

    public function show(Request $request, Package $package): JsonResponse
    {
        abort_unless($package->user_id === $request->user()->id, 404);

        return response()->json(['data' => $this->present($package->load(['events', 'photos', 'paymentReports']))]);
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
            'voided' => $package->voided_at !== null,
            'courier' => $package->courier,
            'store' => $package->store,
            'description' => $package->description,
            'weight_lb' => $package->weight_lb,
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
            // Lo último que se sabe del pago de esta factura, si se reportó.
            'payment' => ($report = $package->paymentReports->first())
                ? PaymentReportController::present($report)
                : null,
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
