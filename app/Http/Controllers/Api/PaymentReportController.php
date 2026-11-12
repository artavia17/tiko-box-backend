<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PaymentReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El cliente reporta que ya pagó una factura.
 *
 * El monto no lo manda el cliente: se toma del paquete. Si se aceptara del
 * formulario, cualquiera podría declarar que pagó un dólar por un envío de
 * cincuenta.
 */
class PaymentReportController extends Controller
{
    public function store(Request $request, Package $package): JsonResponse
    {
        abort_unless($package->user_id === $request->user()->id, 404);

        $this->assertPayable($package);

        $data = $request->validate([
            'method' => ['required', 'string', 'in:'.implode(',', array_keys(PaymentReport::METHODS))],
            'reference' => ['required', 'string', 'max:60'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:8192'],
            'comment' => ['nullable', 'string', 'max:500'],
        ], [
            'method.required' => 'Elegí con qué pagaste.',
            'reference.required' => 'Anotá el número del pago o del comprobante.',
            'paid_on.required' => 'Indicá la fecha del pago.',
            'paid_on.before_or_equal' => 'La fecha del pago no puede ser futura.',
            'receipt.mimes' => 'El comprobante debe ser un PDF o una imagen PNG, WEBP o JPG.',
            'receipt.max' => 'El comprobante no puede pesar más de 8 MB.',
        ]);

        $report = PaymentReport::create([
            'package_id' => $package->id,
            'user_id' => $request->user()->id,
            'method' => $data['method'],
            'reference' => trim($data['reference']),
            'paid_on' => $data['paid_on'],
            'comment' => $data['comment'] ?? null,
            'receipt_path' => $request->file('receipt')
                ?->store("receipts/{$request->user()->id}", 'local'),
            // Del paquete, nunca del formulario.
            'amount' => $package->total,
            'status' => PaymentReport::PENDIENTE,
        ]);

        return response()->json(['data' => self::present($report)], 201);
    }

    /** Devuelve el comprobante al dueño del paquete o al almacén. */
    public function receipt(Request $request, Package $package, PaymentReport $report): StreamedResponse
    {
        abort_unless($report->package_id === $package->id, 404);

        $esDelCliente = $package->user_id === $request->user()->id;
        $esDelAlmacen = $request->user()->tokenCan('staff');

        abort_unless($esDelCliente || $esDelAlmacen, 404);
        abort_unless($report->receipt_path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($report->receipt_path), 404);

        return $disk->download(
            $report->receipt_path,
            'comprobante-'.$package->tracking_number.'.'.pathinfo($report->receipt_path, PATHINFO_EXTENSION),
        );
    }

    /**
     * Un paquete solo admite un reporte nuevo si realmente se debe.
     *
     * Sin esto se podrían apilar avisos sobre una factura ya verificada, y el
     * almacén terminaría revisando pagos que no existen.
     */
    private function assertPayable(Package $package): void
    {
        if ($package->voided_at !== null) {
            throw ValidationException::withMessages([
                'package' => 'Este paquete está anulado: no hay nada que pagar.',
            ]);
        }

        $ultimo = $package->paymentReports()->first();

        if ($ultimo?->status === PaymentReport::VERIFICADO) {
            throw ValidationException::withMessages([
                'package' => 'Esta factura ya está pagada.',
            ]);
        }

        if ($ultimo?->status === PaymentReport::PENDIENTE) {
            throw ValidationException::withMessages([
                'package' => 'Ya reportaste un pago de esta factura y está en revisión.',
            ]);
        }
    }

    /**
     * Cómo se ve un reporte desde cualquier pantalla.
     *
     * @return array<string, mixed>
     */
    public static function present(PaymentReport $report): array
    {
        return [
            'id' => $report->id,
            'status' => $report->status,
            'method' => $report->method,
            'method_label' => PaymentReport::METHODS[$report->method] ?? $report->method,
            'reference' => $report->reference,
            'paid_on' => $report->paid_on->toDateString(),
            'amount' => $report->amount,
            'comment' => $report->comment,
            'has_receipt' => (bool) $report->receipt_path,
            'review_note' => $report->review_note,
            'reviewed_at' => $report->reviewed_at?->toDateTimeString(),
            'reported_at' => $report->created_at->toDateTimeString(),
        ];
    }
}
