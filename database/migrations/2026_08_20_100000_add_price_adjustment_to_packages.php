<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio especial: a un cliente que trae mucho volumen se le puede rebajar el
 * cobro. Se guarda cuánto daba la tarifa, por qué se cambió y quién lo hizo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('original_total', 10, 2)->nullable()->after('total');
            $table->string('price_note')->nullable()->after('original_total');
            $table->foreignId('price_adjusted_by')->nullable()->after('price_note')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('price_adjusted_at')->nullable()->after('price_adjusted_by');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_adjusted_by');
            $table->dropColumn(['original_total', 'price_note', 'price_adjusted_at']);
        });
    }
};
