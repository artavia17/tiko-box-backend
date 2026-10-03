<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número de solicitud: el que identifica el paquete del lado del proveedor.
 *
 * Es otro identificador distinto del tracking del transportista, y es el que
 * sirve para reclamarle a quien nos trae la carga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('request_number', 60)->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('request_number');
        });
    }
};
