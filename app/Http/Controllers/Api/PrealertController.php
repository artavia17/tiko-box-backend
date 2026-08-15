<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prealert;
use App\Models\PrealertItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrealertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $prealerts = $request->user()
            ->prealerts()
            ->with('items')
            ->when($request->query('search'), function ($query, string $search) {
                $query->where('tracking_number', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => collect($prealerts->items())->map($this->present(...)),
            'meta' => [
                'current_page' => $prealerts->currentPage(),
                'last_page' => $prealerts->lastPage(),
                'total' => $prealerts->total(),
                'per_page' => $prealerts->perPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $prealert = DB::transaction(function () use ($request, $data) {
            $prealert = Prealert::create([
                'user_id' => $request->user()->id,
                'tracking_number' => strtoupper($data['tracking_number']),
                'origin' => $data['origin'],
                'courier' => $data['courier'] ?? null,
                'currency' => $data['currency'],
                'expected_arrival' => $data['expected_arrival'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                PrealertItem::create(['prealert_id' => $prealert->id, ...$item]);
            }

            return $prealert;
        });

        // fresh() para traer el estado por defecto que pone la base de datos.
        return response()->json(
            ['data' => $this->present($prealert->fresh()->load('items'))],
            201,
        );
    }

    public function update(Request $request, Prealert $prealert): JsonResponse
    {
        $this->authorizePrealert($request, $prealert);
        $this->ensureEditable($prealert);

        $data = $this->validated($request, $prealert);

        DB::transaction(function () use ($prealert, $data) {
            $prealert->update([
                'tracking_number' => strtoupper($data['tracking_number']),
                'origin' => $data['origin'],
                'courier' => $data['courier'] ?? null,
                'currency' => $data['currency'],
                'expected_arrival' => $data['expected_arrival'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Los artículos se reemplazan por completo.
            $prealert->items()->delete();

            foreach ($data['items'] as $item) {
                PrealertItem::create(['prealert_id' => $prealert->id, ...$item]);
            }
        });

        return response()->json([
            'data' => $this->present($prealert->fresh()->load('items')),
        ]);
    }

    public function destroy(Request $request, Prealert $prealert): JsonResponse
    {
        $this->authorizePrealert($request, $prealert);
        $this->ensureEditable($prealert);

        $prealert->delete();

        return response()->json(['message' => 'Prealerta eliminada.']);
    }

    private function authorizePrealert(Request $request, Prealert $prealert): void
    {
        abort_unless($prealert->user_id === $request->user()->id, 404);
    }

    /**
     * Una vez que el almacén la procesa, la prealerta deja de ser editable:
     * los datos ya se usaron para recibir el paquete.
     */
    private function ensureEditable(Prealert $prealert): void
    {
        if ($prealert->status !== 'pendiente') {
            throw ValidationException::withMessages([
                'status' => 'Esta prealerta ya fue procesada y no se puede modificar.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Prealert $prealert = null): array
    {
        return $request->validate([
            'tracking_number' => [
                'required',
                'string',
                'max:60',
                Rule::unique('prealerts', 'tracking_number')
                    ->where('user_id', $request->user()->id)
                    ->ignore($prealert?->id),
            ],
            'origin' => ['required', 'string', 'max:60'],
            'courier' => ['nullable', 'string', 'max:60'],
            'currency' => ['required', Rule::in(['USD', 'CRC'])],
            'expected_arrival' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ], [
            'tracking_number.unique' => 'Ya tenés una prealerta con ese número de rastreo.',
            'items.required' => 'Agregá al menos un artículo.',
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Prealert $prealert): array
    {
        return [
            'id' => $prealert->id,
            'tracking_number' => $prealert->tracking_number,
            'origin' => $prealert->origin,
            'courier' => $prealert->courier,
            'currency' => $prealert->currency,
            'expected_arrival' => $prealert->expected_arrival?->toDateString(),
            'status' => $prealert->status,
            'notes' => $prealert->notes,
            'declared_value' => $prealert->declaredValue(),
            'created_at' => $prealert->created_at->toDateString(),
            'items' => $prealert->items->map(fn (PrealertItem $item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'description' => $item->description,
                'price' => $item->price,
            ]),
        ];
    }
}
