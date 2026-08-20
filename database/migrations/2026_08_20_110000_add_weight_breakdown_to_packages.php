<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un mismo envío puede traer varios bultos. Se cobra por el peso sumado —
 * cobrar el mínimo por cada uno sería injusto — y se guarda el desglose para
 * poder explicarle al cliente de dónde sale el total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->json('weight_breakdown')->nullable()->after('weight_lb');
            // Sin mínimo: se cobró el peso tal cual.
            $table->boolean('exact_weight')->default(false)->after('weight_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['weight_breakdown', 'exact_weight']);
        });
    }
};
