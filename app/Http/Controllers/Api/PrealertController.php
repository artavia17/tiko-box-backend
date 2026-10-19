<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatalogOption;
use App\Models\Prealert;
use App\Services\AdminNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrealertController extends Controller
{
    /** Únicos formatos aceptados para la factura. */
    private const INVOICE_TYPES = ['pdf', 'png', 'webp', 'jpg', 'jpeg'];

    private const INVOICE_MAX_KB = 8192;

    /**
     * Quiénes pueden traer un paquete a la bodega.
     *
     * Sale del mismo catálogo que usa el almacén al registrar, así que la
     * lista que ve el cliente y la que usa la bodega no se separan.
     */
    public function couriers(): JsonResponse
    {
        $couriers = CatalogOption::where('type', 'carrier')
            ->orderBy('name')
            ->pluck('name');

        return response()->json(['data' => $couriers]);
    }

    public function index(Request $request): JsonResponse
    {
        $prealerts = $request->user()
            ->prealerts()
            // Sin esto, armar la lista haría una consulta por fila.
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

    public function store(Request $request, AdminNotifier $admins): JsonResponse
    {
        $data = $this->validated($request);

        $prealert = DB::transaction(function () use ($request, $data) {
            $prealert = Prealert::create([
                'user_id' => $request->user()->id,
                'tracking_number' => $this->normalizeTracking($data['tracking_number']),
                'courier' => $data['courier'],
                'invoice_path' => $this->storeInvoice($request->file('invoice'), $request->user()->id),
                'origin' => $data['origin'] ?? 'Miami',
                'expected_arrival' => $data['expected_arrival'] ?? null,
            ]);

            $this->saveItems($prealert, $data['items'] ?? []);

            return $prealert;
        });

        $admins->prealertCreated($prealert);

        // fresh() para traer el estado por defecto que pone la base de datos.
        return response()->json(['data' => $this->present($prealert->fresh('items'))], 201);
    }

    public function update(Request $request, Prealert $prealert): JsonResponse
    {
        $this->authorizePrealert($request, $prealert);
        $this->ensureEditable($prealert);

        $data = $this->validated($request, $prealert);

        $invoice = $request->file('invoice');
        $previous = $prealert->invoice_path;

        DB::transaction(function () use ($prealert, $data, $invoice) {
            $prealert->update([
                'tracking_number' => $this->normalizeTracking($data['tracking_number']),
                'courier' => $data['courier'],
                'expected_arrival' => $data['expected_arrival'] ?? null,
                // Si no mandan una factura nueva, se conserva la que ya estaba.
                ...($invoice ? ['invoice_path' => $this->storeInvoice($invoice, $prealert->user_id)] : []),
            ]);

            $this->saveItems($prealert, $data['items'] ?? []);
        });

        if ($invoice && $previous) {
            Storage::disk('local')->delete($previous);
        }

        return response()->json(['data' => $this->present($prealert->fresh('items'))]);
    }

    public function destroy(Request $request, Prealert $prealert): JsonResponse
    {
        $this->authorizePrealert($request, $prealert);
        $this->ensureEditable($prealert);

        if ($prealert->invoice_path) {
            Storage::disk('local')->delete($prealert->invoice_path);
        }

        $prealert->delete();

        return response()->json(['message' => 'Prealerta eliminada.']);
    }

    /**
     * Entrega la factura. Va por acá y no por una URL pública porque el
     * archivo trae los datos de compra del cliente.
     */
    public function invoice(Request $request, Prealert $prealert): StreamedResponse
    {
        // El personal la revisa al recibir la caja; el resto, solo la propia.
        abort_unless(
            $prealert->user_id === $request->user()->id || $request->user()->isStaff(),
            404,
        );

        abort_unless($prealert->invoice_path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($prealert->invoice_path), 404);

        return $disk->response(
            $prealert->invoice_path,
            'factura-'.$prealert->tracking_number.'.'.pathinfo($prealert->invoice_path, PATHINFO_EXTENSION),
            ['Content-Disposition' => 'inline'],
        );
    }

    /** Guarda la factura fuera del disco público, separada por cliente. */
    private function storeInvoice(UploadedFile $file, int $userId): string
    {
        return $file->store("invoices/{$userId}", 'local');
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
        // Al editar, la factura solo se valida si mandan una nueva.
        $invoiceRules = [
            $prealert ? 'nullable' : 'required',
            File::types(self::INVOICE_TYPES)->max(self::INVOICE_MAX_KB),
        ];

        return $request->validate([
            'tracking_number' => [
                'required',
                'string',
                'max:60',
                Rule::unique('prealerts', 'tracking_number')
                    ->where('user_id', $request->user()->id)
                    ->ignore($prealert?->id),
            ],
            'invoice' => $invoiceRules,
            'origin' => ['nullable', 'string', 'max:60'],
            'courier' => ['required', 'string', 'max:60'],

            // El contenido declarado: es lo que aduana usa para calcular
            // impuestos sin aforar el paquete a ojo. Se pide, pero no se
            // obliga: muchos clientes prealertan apenas compran, cuando
            // todavía no tienen el detalle a mano.
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.amount' => ['required', 'numeric', 'gt:0', 'max:99999.99'],

            // Se acepta hoy; nadie prealerta algo que ya llegó, y un plazo
            // muy lejano suele ser un dedazo en el año. Al editar se conserva
            // la fecha original aunque ya haya pasado: obligar a cambiarla
            // haría perder el dato real de cuándo se esperaba.
            'expected_arrival' => array_filter([
                'nullable',
                'date',
                $this->keepsOriginalDate($request, $prealert) ? null : 'after_or_equal:today',
                'before_or_equal:'.now()->addDays(180)->toDateString(),
            ]),

        ], [
            'tracking_number.unique' => 'Ya tenés una prealerta con ese número de rastreo.',
            'courier.required' => 'Elegí quién trae el paquete a Miami.',
            'items.*.description.required' => 'Escribí qué viene en el paquete.',
            'items.*.amount.gt' => 'El valor del artículo tiene que ser mayor que cero.',
            'expected_arrival.after_or_equal' => 'La fecha de llegada no puede ser anterior a hoy.',
            'invoice.required' => 'Subí la factura de la compra.',
            'invoice.mimes' => 'La factura debe ser un PDF o una imagen PNG, WEBP o JPG.',
            'invoice.max' => 'La factura no puede pesar más de 8 MB.',
        ]);
    }

    /** @return array<string, mixed> */
    /** ¿La edición deja la fecha tal como estaba guardada? */
    private function keepsOriginalDate(Request $request, ?Prealert $prealert): bool
    {
        if (! $prealert?->expected_arrival) {
            return false;
        }

        $sent = $request->input('expected_arrival');

        return $sent && str_starts_with(
            (string) $sent,
            $prealert->expected_arrival->toDateString(),
        );
    }

    /** Sin espacios a los lados y en mayúsculas, para que emparejar funcione. */
    private function normalizeTracking(string $tracking): string
    {
        return strtoupper(trim($tracking));
    }

    /**
     * Reemplaza el contenido declarado.
     *
     * Se borra y se vuelve a escribir en vez de ir casando línea por línea:
     * son pocos renglones y el cliente los edita como un bloque. Sin
     * renglones queda sin contenido declarado, que ahora es válido.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveItems(Prealert $prealert, array $items): void
    {
        $prealert->items()->delete();

        $prealert->items()->createMany(array_map(fn (array $item) => [
            'quantity' => (int) $item['quantity'],
            'description' => trim((string) $item['description']),
            'amount' => round((float) $item['amount'], 2),
        ], $items));
    }

    private function present(Prealert $prealert): array
    {
        return [
            'id' => $prealert->id,
            'consolidation_id' => $prealert->consolidation_id,
            'tracking_number' => $prealert->tracking_number,
            'origin' => $prealert->origin,
            'courier' => $prealert->courier,
            'expected_arrival' => $prealert->expected_arrival?->toDateString(),
            'items' => $prealert->items->map(fn ($item) => [
                'quantity' => $item->quantity,
                'description' => $item->description,
                'amount' => $item->amount,
                'subtotal' => $item->subtotal(),
            ])->values(),
            'declared_value' => $prealert->declaredValue(),
            'status' => $prealert->status,
            'has_invoice' => (bool) $prealert->invoice_path,
            'invoice_type' => $prealert->invoice_path
                ? strtolower(pathinfo($prealert->invoice_path, PATHINFO_EXTENSION))
                : null,
            'created_at' => $prealert->created_at->toDateString(),
        ];
    }
}
