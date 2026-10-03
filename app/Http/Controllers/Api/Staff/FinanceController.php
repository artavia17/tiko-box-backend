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
 * Nada de esto se calcula con números fijos. El costo y el tipo de cambio
 * viven en cada paquete, así que la ganancia de un mes ya cerrado no cambia
 * cuando se mueve el dólar o se renegocia la tarifa con el proveedor.
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

        return response()->json([
            'data' => [
                'period' => ['year' => $year, 'month' => $month],
                'rows' => $rows,
                'totals' => [
                    'packages' => $rows->count(),
                    'weight_lb' => round((float) $rows->sum('weight_lb'), 2),
                    'cost' => round((float) $rows->sum('cost'), 2),
                    'revenue' => round((float) $rows->sum('revenue'), 2),
                    'profit' => round((float) $rows->sum('profit'), 2),
                    'cost_crc' => round((float) $rows->sum('cost_crc'), 2),
                    'revenue_crc' => round((float) $rows->sum('revenue_crc'), 2),
                    'profit_crc' => round((float) $rows->sum('profit_crc'), 2),
                ],
                // El resumen del año, mes por mes: es lo que deja ver si el
                // negocio mejora o solo creció el movimiento.
                'months' => $this->byMonth($year),
                'estimated' => $rows->where('estimated', true)->count(),
            ],
        ]);
    }

    /** Corrige el costo de un paquete cuando llega la factura del proveedor. */
    public function updateCost(Request $request, Package $package): JsonResponse
    {
        $data = $request->validate([
            'cost' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $package->update(['cost' => round((float) $data['cost'], 2)]);

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

                return [
                    'month' => (int) $month,
                    'label' => Carbon::create(null, (int) $month, 1)->locale('es')->monthName,
                    'packages' => $rows->count(),
                    'cost' => round((float) $rows->sum('cost'), 2),
                    'revenue' => round((float) $rows->sum('revenue'), 2),
                    'profit' => round((float) $rows->sum('profit'), 2),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function present(Package $package): array
    {
        // Un paquete de antes de que existiera esta pantalla no tiene costo
        // guardado: se estima con la tarifa actual y se marca como tal, para
        // que nadie tome por cerrado un número que todavía no lo es.
        $estimated = $package->cost === null;

        $cost = $estimated
            ? round((float) $package->weight_lb * (float) config('tikabox.cost_per_pound'), 2)
            : (float) $package->cost;

        $rate = (float) ($package->exchange_rate ?? config('tikabox.exchange_rate'));
        $revenue = (float) $package->total;
        $profit = round($revenue - $cost, 2);

        return [
            'id' => $package->id,
            'tracking_number' => $package->tracking_number,
            'customer' => $package->user?->fullName(),
            'locker_code' => $package->user?->locker_code,
            'weight_lb' => (float) $package->weight_lb,
            'cost' => $cost,
            'revenue' => $revenue,
            'profit' => $profit,
            'exchange_rate' => $rate,
            'cost_crc' => round($cost * $rate, 2),
            'revenue_crc' => round($revenue * $rate, 2),
            'profit_crc' => round($profit * $rate, 2),
            'received_at' => $package->received_at?->toDateString(),
            'status' => $package->status,
            'estimated' => $estimated,
        ];
    }
}
