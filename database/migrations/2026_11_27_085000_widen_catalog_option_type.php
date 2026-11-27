<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La lista deja de aceptar solo transportistas y tiendas.
 *
 * El tipo era un enum de dos valores, así que agregar las formas de pago lo
 * rechazaba en la base. Pasa a texto: qué tipos valen lo decide el código,
 * que es donde se puede leer, y no una restricción que obliga a migrar cada
 * vez que aparece una lista nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_options', function (Blueprint $table) {
            $table->string('type', 40)->change();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_options', function (Blueprint $table) {
            $table->enum('type', ['carrier', 'store'])->change();
        });
    }
};
