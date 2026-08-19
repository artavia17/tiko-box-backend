<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Prealert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Las prealertas vistas desde el almacén: lo que los clientes avisaron que
 * viene en camino, para saber qué esperar y a quién pertenece cada caja.
 */
class StaffPrealertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $dates = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            // Las que el cliente anunció sin decir cuándo llegan.
            'undated' => ['nullable', 'boolean'],
        ]);

        $prealerts = Prealert::with('user')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('tracking_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($status, ['pendiente', 'recibido', 'en_transito', 'entregado'], true),
                fn ($query) => $query->where('status', $status))
            // Por el día en que el cliente dice que llega, que es lo que el
            // almacén necesita saber para esperarlo.
            ->when($dates['undated'] ?? false,
                fn ($query) => $query->whereNull('expected_arrival'))
            ->when($dates['from'] ?? null,
                fn ($query, $from) => $query->whereDate('expected_arrival', '>=', Carbon::parse($from)))
            ->when($dates['to'] ?? null,
                fn ($query, $to) => $query->whereDate('expected_arrival', '<=', Carbon::parse($to)))
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => collect($prealerts->items())->map($this->present(...)),
            'meta' => [
                'current_page' => $prealerts->currentPage(),
                'last_page' => $prealerts->lastPage(),
                'total' => $prealerts->total(),
                'pendientes' => Prealert::where('status', 'pendiente')->count(),
                'llegan_hoy' => Prealert::where('status', 'pendiente')
                    ->whereDate('expected_arrival', today())
                    ->count(),
                'atrasadas' => Prealert::where('status', 'pendiente')
                    ->whereDate('expected_arrival', '<', today())
                    ->count(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Prealert $prealert): array
    {
        return [
            'id' => $prealert->id,
            'tracking_number' => $prealert->tracking_number,
            'origin' => $prealert->origin,
            'expected_arrival' => $prealert->expected_arrival?->toDateString(),
            'status' => $prealert->status,
            'has_invoice' => (bool) $prealert->invoice_path,
            'invoice_type' => $prealert->invoice_path
                ? strtolower(pathinfo($prealert->invoice_path, PATHINFO_EXTENSION))
                : null,
            'created_at' => $prealert->created_at->toDateString(),
            'customer' => [
                'id' => $prealert->user?->id,
                'full_name' => $prealert->user?->fullName(),
                'phone' => $prealert->user?->phone,
                'email' => $prealert->user?->email,
            ],
        ];
    }
}
