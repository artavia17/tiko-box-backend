<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Lo que deja cada paquete: cuánto costó, cuánto se cobró y la diferencia.
 *
 * El costo lo digita quien conoce lo que se pagó por el envío; acá nunca se
 * estima. Un paquete sin costo queda pendiente y fuera de los totales, para
 * que la ganancia que se muestra sea plata de verdad y no un supuesto.
 *
 * El costo y el tipo de cambio viven en cada paquete, así que la ganancia de
 * un mes ya cerrado no cambia cuando se mueve el dólar.
 */
class FinanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year = (int) ($data['year'] ?? now()->year);
        $month = isset($data['month']) ? (int) $data['month'] : null;

        [$from, $to] = $month
            ? [Carbon::create($year, $month, 1)->startOfMonth(), Carbon::create($year, $month, 1)->endOfMonth()]
            : [Carbon::create($year, 1, 1)->startOfYear(), Carbon::create($year, 1, 1)->endOfYear()];

        $packages = Package::with('user:id,first_name,last_name,second_last_name,locker_code')
            ->whereBetween('received_at', [$from, $to])
            ->orderByDesc('received_at')
            ->get();

        $rows = $packages->map($this->present(...));
        // Una venta anulada no es plata, y una sin costo conocido daría una
        // ganancia inflada: ninguna de las dos entra en los totales.
        $costed = $rows->where('voided', false)->where('cost', '!==', null);

        return response()->json([
            'data' => [
                'period' => ['year' => $year, 'month' => $month],
                'rows' => $rows,
                'totals' => [
                    'packages' => $costed->count(),
                    'weight_lb' => round((float) $costed->sum('weight_lb'), 2),
                    'cost' => round((float) $costed->sum('cost'), 2),
                    'revenue' => round((float) $costed->sum('revenue'), 2),
                    'profit' => round((float) $costed->sum('profit'), 2),
                    'cost_crc' => round((float) $costed->sum('cost_crc'), 2),
                    'revenue_crc' => round((float) $costed->sum('revenue_crc'), 2),
                    'profit_crc' => round((float) $costed->sum('profit_crc'), 2),
                ],
                // El resumen del año, mes por mes: es lo que deja ver si el
                // negocio mejora o solo creció el movimiento.
                'months' => $this->byMonth($year),
                // Cuántos siguen esperando que alguien diga qué costaron.
                'pending' => $rows->where('voided', false)->where('cost', null)->count(),
                'voided' => $rows->where('voided', true)->count(),
            ],
        ]);
    }

    /**
     * Corrige el costo o el tipo de cambio de un paquete.
     *
     * Lo primero llega con la factura del proveedor; lo segundo, cuando se
     * registró con el cambio equivocado. Solo se toca lo que viene en la
     * petición: mandar uno no debería borrar el otro.
     */
    public function update(Request $request, Package $package): JsonResponse
    {
        $data = $request->validate([
            'cost' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'exchange_rate' => ['nullable', 'numeric', 'min:1', 'max:5000'],
            'total' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'collected_by' => ['nullable', 'string', 'max:80'],
            'request_number' => ['nullable', 'string', 'max:60'],
        ]);

        $changes = [];

        if ($request->has('cost')) {
            $changes['cost'] = round((float) $data['cost'], 2);
        }

        if ($request->has('request_number')) {
            $changes['request_number'] = trim((string) $data['request_number']) ?: null;
        }

        if ($request->has('collected_by')) {
            $changes['collected_by'] = trim((string) $data['collected_by']) ?: null;
        }

        if ($request->has('exchange_rate')) {
            $changes['exchange_rate'] = round((float) $data['exchange_rate'], 2);
        }

        if ($request->has('total')) {
            $total = round((float) $data['total'], 2);

            // Se guarda lo que daba la tarifa la primera vez que se baja el
            // precio, y después no se pisa: si no, el descuento que ve el
            // cliente se iría achicando con cada corrección.
            $changes['original_total'] = $package->original_total ?? $package->total;
            $changes['total'] = $total;
            $changes['price_adjusted_by'] = $request->user()->id;
            $changes['price_adjusted_at'] = now();

            // Volver al precio de lista deja de ser un descuento.
            if ($total >= (float) $changes['original_total']) {
                $changes['original_total'] = null;
            }
        }

        $package->update($changes);

        return response()->json([
            'data' => $this->present($package->fresh()->load('user')),
        ]);
    }

    /** Anula la venta: el paquete existe, pero no entra en la caja. */
    public function void(Request $request, Package $package): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $package->update([
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'data' => $this->present($package->fresh()->load('user')),
        ]);
    }

    /** Deshace la anulación, por si se anuló el que no era. */
    public function restore(Package $package): JsonResponse
    {
        $package->update([
            'voided_at' => null,
            'voided_by' => null,
            'void_reason' => null,
        ]);

        return response()->json([
            'data' => $this->present($package->fresh()->load('user')),
        ]);
    }

    /**
     * Totales por mes del año pedido.
     *
     * @return list<array<string, mixed>>
     */
    private function byMonth(int $year): array
    {
        $packages = Package::whereBetween('received_at', [
            Carbon::create($year, 1, 1)->startOfYear(),
            Carbon::create($year, 1, 1)->endOfYear(),
        ])->get();

        return $packages
            ->groupBy(fn (Package $package) => $package->received_at?->month ?? 0)
            ->sortKeys()
            ->map(function ($group, $month) {
                $rows = $group->map($this->present(...));
                $costed = $rows->where('voided', false)->where('cost', '!==', null);

                return [
                    'month' => (int) $month,
                    'label' => Carbon::create(null, (int) $month, 1)->locale('es')->monthName,
                    // Cuenta los mismos paquetes que suman en la plata de al
                    // lado: una fila donde el número y el monto hablan de
                    // conjuntos distintos se lee como un error de cálculo.
                    'packages' => $costed->count(),
                    'pending' => $rows->where('voided', false)->where('cost', null)->count(),
                    'cost' => round((float) $costed->sum('cost'), 2),
                    'revenue' => round((float) $costed->sum('revenue'), 2),
                    'profit' => round((float) $costed->sum('profit'), 2),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function present(Package $package): array
    {
        // Sin costo digitado no hay ganancia que mostrar: queda pendiente.
        $cost = $package->cost === null ? null : (float) $package->cost;
        $rate = (float) ($package->exchange_rate ?? config('tikabox.exchange_rate'));
        $revenue = (float) $package->total;
        $profit = $cost === null ? null : round($revenue - $cost, 2);

        return [
            'id' => $package->id,
            'tracking_number' => $package->tracking_number,
            'request_number' => $package->request_number,
            'customer' => $package->user?->fullName(),
            'locker_code' => $package->user?->locker_code,
            'weight_lb' => (float) $package->weight_lb,
            'cost' => $cost,
            'revenue' => $revenue,
            'profit' => $profit,
            'exchange_rate' => $rate,
            'cost_crc' => $cost === null ? null : round($cost * $rate, 2),
            'revenue_crc' => round($revenue * $rate, 2),
            'profit_crc' => $profit === null ? null : round($profit * $rate, 2),
            'received_at' => $package->received_at?->toDateString(),
            'status' => $package->status,
            'collected_by' => $package->collected_by,
            'voided' => $package->voided_at !== null,
            'void_reason' => $package->void_reason,
        ];
    }
}
