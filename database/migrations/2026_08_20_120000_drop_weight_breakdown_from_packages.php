<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El desglose por bulto no llegó a usarse: el almacén pesa todo junto y marca
 * el envío como consolidado, que es lo que quita el mínimo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('weight_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->json('weight_breakdown')->nullable()->after('weight_lb');
        });
    }
};
