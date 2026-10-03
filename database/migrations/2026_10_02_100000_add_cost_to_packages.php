<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que cada paquete le cuesta al negocio, para poder ver la ganancia.
 *
 * El tipo de cambio se guarda junto al paquete y no en un solo lugar: si
 * viviera suelto, el colón de un mes cerrado cambiaría cada vez que el dólar
 * se mueve, y los números de setiembre dejarían de ser los de setiembre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->nullable()->after('total');
            $table->decimal('exchange_rate', 10, 2)->nullable()->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['cost', 'exchange_rate']);
        });
    }
};
