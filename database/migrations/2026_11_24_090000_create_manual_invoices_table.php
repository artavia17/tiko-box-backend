<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturas emitidas a mano, para paquetes que nunca se registraron.
 *
 * Van en su propia tabla y no en packages: no son inventario, nadie las
 * movió por el almacén y no deben aparecer en las cifras de operación. Lo
 * que se guarda es el documento que se le entregó a alguien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_invoices', function (Blueprint $table) {
            $table->id();
            // Quién la emitió: una factura la firma alguien, no el sistema.
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            // Si se pudo asociar a una cuenta. Puede no existir: el caso es
            // justamente el de un paquete que quedó fuera del sistema.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('customer_name');
            $table->string('locker_code');
            $table->string('tracking_number');
            $table->string('order_number')->nullable();
            $table->string('store')->nullable();

            // La fecha que factura, no la de emisión.
            $table->date('issued_on');
            $table->decimal('weight_lb', 8, 2);
            $table->decimal('total', 10, 2);
            $table->string('note')->nullable();

            $table->timestamps();

            $table->index('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_invoices');
    }
};
