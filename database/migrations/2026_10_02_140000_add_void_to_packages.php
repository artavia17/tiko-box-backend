<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas anuladas: un paquete que se perdió, se devolvió o nunca se cobró.
 *
 * Se marca en vez de borrarse. La caja tiene que poder explicar después por
 * qué un paquete que existe no sumó plata, y un registro borrado no explica
 * nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('exchange_rate');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};
