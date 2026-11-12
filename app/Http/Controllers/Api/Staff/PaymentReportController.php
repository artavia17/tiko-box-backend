<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Api\PaymentReportController as Presenter;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PaymentReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** El almacén revisa los pagos que reportan los clientes. */
class PaymentReportController extends Controller
{
    /** Los reportes a la espera de revisión, el más viejo primero. */
    public function index(): JsonResponse
    {
        $reports = PaymentReport::where('status', PaymentReport::PENDIENTE)
            ->with(['package.user'])
            ->oldest('id')
            ->get();

        return response()->json([
            'data' => $reports->map(fn (PaymentReport $report) => [
                ...Presenter::present($report),
                'package_id' => $report->package_id,
                'tracking_number' => $report->package->tracking_number,
                'customer' => $report->package->user->fullName(),
            ]),
        ]);
    }

    public function verify(Request $request, Package $package, PaymentReport $report): JsonResponse
    {
        return $this->review($request, $package, $report, PaymentReport::VERIFICADO);
    }

    public function reject(Request $request, Package $package, PaymentReport $report): JsonResponse
    {
        $request->validate([
            'review_note' => ['required', 'string', 'max:200'],
        ], [
            'review_note.required' => 'Decile al cliente por qué no se aceptó el pago.',
        ]);

        return $this->review($request, $package, $report, PaymentReport::RECHAZADO);
    }

    private function review(Request $request, Package $package, PaymentReport $report, string $status): JsonResponse
    {
        abort_unless($report->package_id === $package->id, 404);

        // Solo se revisa una vez: verificar dos veces un mismo reporte deja
        // el historial diciendo cosas distintas según quién lo mire.
        if ($report->status !== PaymentReport::PENDIENTE) {
            throw ValidationException::withMessages([
                'report' => 'Este reporte ya fue revisado.',
            ]);
        }

        $report->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('review_note'),
        ]);

        return response()->json(['data' => Presenter::present($report->fresh())]);
    }
}
