<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reportes de pago: el cliente avisa que ya pagó una factura.
 *
 * No se cobra nada acá. El dinero se mueve por fuera (SINPE, transferencia,
 * efectivo) y esta tabla guarda la evidencia para que el almacén la verifique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('method');
            $table->string('reference');
            $table->date('paid_on');
            $table->string('receipt_path')->nullable();
            $table->text('comment')->nullable();

            // El total queda congelado: si después se le hace precio al
            // paquete, lo que el cliente dijo haber pagado no cambia solo.
            $table->decimal('amount', 10, 2);

            $table->string('status')->default('pendiente')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();

            $table->timestamps();

            // Un solo reporte vivo por paquete; los rechazados se reemplazan.
            $table->index(['package_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reports');
    }
};
