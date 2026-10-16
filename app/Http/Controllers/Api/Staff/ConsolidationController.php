<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Consolidation;
use App\Models\Package;
use App\Models\Prealert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lo que el almacén hace con las consolidaciones.
 *
 * El cliente arma el grupo; la bodega es la que lo cierra, porque es la
 * única que sabe qué llegó de verdad y cuánto pesa junto.
 */
class ConsolidationController extends Controller
{
    /** Las que están esperando que alguien las cierre. */
    public function index(Request $request): JsonResponse
    {
        $consolidations = Consolidation::with(['prealerts', 'user'])
            ->whereNull('processed_at')
            ->latest()
            ->get()
            // El estado se deduce de fechas, así que el filtro va en memoria.
            ->filter(fn (Consolidation $item) => in_array($item->status(), [
                Consolidation::SOLICITADO,
                Consolidation::PENDIENTE,
                Consolidation::COMPLETADO,
            ], true));

        return response()->json([
            'data' => $consolidations->map(fn (Consolidation $item) => [
                'id' => $item->id,
                'status' => $item->status(),
                'customer' => $item->user?->fullName(),
                'locker_code' => $item->user?->locker_code,
                'packages_count' => $item->prealerts->count(),
                'received_count' => $item->receivedCount(),
                'expires_at' => $item->expires_at?->toDateString(),
                'trackings' => $item->prealerts->pluck('tracking_number')->values(),
            ])->values(),
        ]);
    }

    /**
     * Cierra la consolidación: registra el paquete que la representa.
     *
     * El peso lo toma la bodega del grupo ya empacado, que es el sentido de
     * consolidar: se cobra una vez por el bulto y no una vez por caja.
     */
    public function process(Request $request, Consolidation $consolidation): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403);

        $data = $request->validate([
            'weight_lb' => ['required', 'numeric', 'min:0.01', 'max:500'],
            'tracking_number' => ['required', 'string', 'max:60'],
        ]);

        $consolidation->load('prealerts');

        if ($consolidation->processed_at) {
            throw ValidationException::withMessages([
                'consolidation' => 'Esta consolidación ya se procesó.',
            ]);
        }

        $arrived = $consolidation->prealerts->where('status', '!=', 'pendiente');

        if ($arrived->isEmpty()) {
            throw ValidationException::withMessages([
                'consolidation' => 'Todavía no llegó ningún paquete de esta consolidación.',
            ]);
        }

        $package = DB::transaction(function () use ($request, $consolidation, $data, $arrived) {
            $weight = round((float) $data['weight_lb'], 2);
            $rate = (float) config('tikabox.price_per_pound');

            $package = Package::create([
                'user_id' => $consolidation->user_id,
                'registered_by' => $request->user()->id,
                'tracking_number' => strtoupper(trim($data['tracking_number'])),
                'description' => "Consolidado #{$consolidation->id}: {$arrived->count()} paquetes",
                'weight_lb' => $weight,
                // Consolidado: se cobra el peso exacto del bulto, sin aplicar
                // el mínimo de una libra a cada caja por separado.
                'exact_weight' => true,
                'price_per_pound' => $rate,
                'total' => round($weight * $rate, 2),
                'exchange_rate' => (float) config('tikabox.exchange_rate'),
                'status' => 'recibido',
                'received_at' => now(),
            ]);

            $consolidation->update([
                'processed_at' => now(),
                'generated_package_id' => $package->id,
            ]);

            // Las que no llegaron se liberan: siguen su camino por separado.
            Prealert::where('consolidation_id', $consolidation->id)
                ->where('status', 'pendiente')
                ->update(['consolidation_id' => null]);

            return $package;
        });

        return response()->json([
            'data' => [
                'consolidation_id' => $consolidation->id,
                'package_id' => $package->id,
                'tracking_number' => $package->tracking_number,
                'total' => (float) $package->total,
            ],
        ], 201);
    }
}
