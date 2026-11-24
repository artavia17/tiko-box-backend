<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\ManualInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Facturas emitidas a mano, para paquetes que nunca se registraron.
 *
 * El PDF lo arma el navegador; acá solo vive lo que dice la factura, para
 * poder volver a emitirla igual meses después.
 */
class ManualInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invoices = ManualInvoice::with('issuer')
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%")
                        ->orWhere('order_number', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => collect($invoices->items())->map($this->present(...)),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $invoice = ManualInvoice::create([
            ...$this->validated($request),
            'issued_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($invoice->load('issuer'))], 201);
    }

    public function update(Request $request, ManualInvoice $invoice): JsonResponse
    {
        $this->assertEditable($invoice);

        $invoice->update($this->validated($request));

        return response()->json(['data' => $this->present($invoice->fresh()->load('issuer'))]);
    }

    public function destroy(ManualInvoice $invoice): JsonResponse
    {
        $this->assertEditable($invoice);

        $invoice->delete();

        return response()->json(['message' => 'Factura eliminada.']);
    }

    /**
     * Una factura vieja no se toca.
     *
     * Pasado el día ya se le entregó a alguien —y a veces a Amazon—, así que
     * cambiarla o borrarla sería alterar un documento que ya está en manos
     * de otro.
     */
    private function assertEditable(ManualInvoice $invoice): void
    {
        if (! $invoice->isEditable()) {
            throw ValidationException::withMessages([
                'invoice' => 'Esta factura tiene más de un día: ya se entregó y no se puede cambiar.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'locker_code' => ['required', 'string', 'max:30'],
            'tracking_number' => ['required', 'string', 'max:60'],
            'order_number' => ['nullable', 'string', 'max:60'],
            'store' => ['nullable', 'string', 'max:60'],
            'issued_on' => ['required', 'date'],
            'weight_lb' => ['required', 'numeric', 'min:0.01'],
            'total' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:200'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [
            'customer_name.required' => 'Escribí a nombre de quién va la factura.',
            'tracking_number.required' => 'Falta el número de rastreo.',
            'weight_lb.required' => 'Falta el peso.',
        ]);
    }

    /** @return array<string, mixed> */
    private function present(ManualInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'customer_name' => $invoice->customer_name,
            'locker_code' => $invoice->locker_code,
            'tracking_number' => $invoice->tracking_number,
            'order_number' => $invoice->order_number,
            'store' => $invoice->store,
            'issued_on' => $invoice->issued_on->toDateString(),
            'weight_lb' => $invoice->weight_lb,
            'total' => $invoice->total,
            'note' => $invoice->note,
            'issued_by' => $invoice->issuer?->fullName(),
            // Que la pantalla no vuelva a calcular el plazo por su cuenta:
            // dos relojes distintos terminan diciendo cosas distintas.
            'editable' => $invoice->isEditable(),
            'created_at' => $invoice->created_at->toDateTimeString(),
        ];
    }
}
