<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prealert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

    public function index(Request $request): JsonResponse
    {
        $prealerts = $request->user()
            ->prealerts()
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

        $prealert = Prealert::create([
            'user_id' => $request->user()->id,
            'tracking_number' => strtoupper($data['tracking_number']),
            'invoice_path' => $this->storeInvoice($request->file('invoice'), $request->user()->id),
            'origin' => $data['origin'] ?? 'Miami',
            'expected_arrival' => $data['expected_arrival'] ?? null,
        ]);

        // fresh() para traer el estado por defecto que pone la base de datos.
        return response()->json(['data' => $this->present($prealert->fresh())], 201);
    }

    public function update(Request $request, Prealert $prealert): JsonResponse
    {
        $this->authorizePrealert($request, $prealert);
        $this->ensureEditable($prealert);

        $data = $this->validated($request, $prealert);

        $invoice = $request->file('invoice');
        $previous = $prealert->invoice_path;

        $prealert->update([
            'tracking_number' => strtoupper($data['tracking_number']),
            'expected_arrival' => $data['expected_arrival'] ?? null,
            // Si no mandan una factura nueva, se conserva la que ya estaba.
            ...($invoice ? ['invoice_path' => $this->storeInvoice($invoice, $prealert->user_id)] : []),
        ]);

        if ($invoice && $previous) {
            Storage::disk('local')->delete($previous);
        }

        return response()->json(['data' => $this->present($prealert->fresh())]);
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
            'expected_arrival' => ['nullable', 'date'],
        ], [
            'tracking_number.unique' => 'Ya tenés una prealerta con ese número de rastreo.',
            'invoice.required' => 'Subí la factura de la compra.',
            'invoice.mimes' => 'La factura debe ser un PDF o una imagen PNG, WEBP o JPG.',
            'invoice.max' => 'La factura no puede pesar más de 8 MB.',
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
        ];
    }
}
