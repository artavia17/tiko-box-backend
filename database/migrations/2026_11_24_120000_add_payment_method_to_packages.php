<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cómo se pagó el paquete al entregarlo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Efectivo, SINPE o transferencia. Nulo en lo ya entregado antes
            // de que esto existiera: no se va a inventar cómo se pagó.
            $table->string('payment_method')->nullable()->after('collected_by');
            $table->string('payment_reference')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_reference']);
        });
    }
};
