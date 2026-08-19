<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Números del negocio: solo para quien administra. */
class AdminController extends Controller
{
    /**
     * Resumen del negocio en el período pedido.
     *
     * El dinero se cuenta por paquete entregado, que es cuando se cobra;
     * lo demás queda como pendiente.
     */
    public function stats(Request $request): JsonResponse
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

        $delivered = Package::where('status', 'entregado')->whereBetween('delivered_at', [$from, $to]);

        return response()->json([
            'data' => [
                'period' => [
                    'year' => $year,
                    'month' => $month,
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'revenue' => [
                    'collected' => round((float) (clone $delivered)->sum('total'), 2),
                    'packages' => (clone $delivered)->count(),
                    'pending' => round((float) Package::where('status', '!=', 'entregado')->sum('total'), 2),
                    'weight_lb' => round((float) (clone $delivered)->sum('weight_lb'), 2),
                ],
                'packages' => [
                    'recibido' => Package::where('status', 'recibido')->count(),
                    'en_transito' => Package::where('status', 'en_transito')->count(),
                    'listo' => Package::where('status', 'listo')->count(),
                    'entregado' => Package::where('status', 'entregado')->count(),
                    'registrados_periodo' => Package::whereBetween('received_at', [$from, $to])->count(),
                ],
                'customers' => [
                    'total' => User::where('role', 'cliente')->count(),
                    'verificados' => User::where('role', 'cliente')->whereNotNull('email_verified_at')->count(),
                    'nuevos_periodo' => User::where('role', 'cliente')->whereBetween('created_at', [$from, $to])->count(),
                    'con_paquetes' => User::where('role', 'cliente')->has('packages')->count(),
                ],
                'staff' => [
                    'empleados' => User::where('role', 'empleado')->count(),
                    'admins' => User::where('role', 'admin')->count(),
                ],
                'series' => $this->series($year, $month),
            ],
        ]);
    }

    /**
     * Serie para el gráfico: por mes si se mira un año, por día si se mira
     * un mes.
     *
     * @return list<array{label: string, revenue: float, packages: int}>
     */
    private function series(int $year, ?int $month): array
    {
        $driver = DB::connection()->getDriverName();
        $unit = $month ? 'day' : 'month';

        // SQLite en las pruebas, MySQL en el servidor: la función difiere.
        $expression = $driver === 'sqlite'
            ? ($month ? "CAST(strftime('%d', delivered_at) AS INTEGER)" : "CAST(strftime('%m', delivered_at) AS INTEGER)")
            : ($month ? 'DAY(delivered_at)' : 'MONTH(delivered_at)');

        $from = $month
            ? Carbon::create($year, $month, 1)->startOfMonth()
            : Carbon::create($year, 1, 1)->startOfYear();

        $to = $month
            ? Carbon::create($year, $month, 1)->endOfMonth()
            : Carbon::create($year, 1, 1)->endOfYear();

        $rows = Package::where('status', 'entregado')
            ->whereBetween('delivered_at', [$from, $to])
            ->selectRaw("{$expression} as bucket, SUM(total) as revenue, COUNT(*) as packages")
            ->groupBy('bucket')
            ->pluck(DB::raw('revenue'), 'bucket')
            ->all();

        $counts = Package::where('status', 'entregado')
            ->whereBetween('delivered_at', [$from, $to])
            ->selectRaw("{$expression} as bucket, COUNT(*) as packages")
            ->groupBy('bucket')
            ->pluck('packages', 'bucket')
            ->all();

        $months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $length = $unit === 'day' ? $to->day : 12;

        return array_map(function (int $index) use ($rows, $counts, $unit, $months) {
            $bucket = $index + 1;

            return [
                'label' => $unit === 'day' ? (string) $bucket : $months[$index],
                'revenue' => round((float) ($rows[$bucket] ?? 0), 2),
                'packages' => (int) ($counts[$bucket] ?? 0),
            ];
        }, range(0, $length - 1));
    }
}
