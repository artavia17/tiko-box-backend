<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Constancia de la entrega: quién recibió el paquete y su firma. Es el
 * respaldo de que la caja salió del almacén y a manos de quién.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('delivered_to_name')->nullable()->after('delivered_at');
            $table->string('delivered_to_identification')->nullable()->after('delivered_to_name');
            $table->string('signature_path')->nullable()->after('delivered_to_identification');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'delivered_to_name',
                'delivered_to_identification',
                'signature_path',
            ]);
        });
    }
};
