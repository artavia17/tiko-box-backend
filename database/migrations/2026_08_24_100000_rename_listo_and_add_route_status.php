<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Listo" pasa a llamarse "bodega": el paquete ya está en la bodega de Costa
 * Rica, que no es lo mismo que estar en camino al cliente. Ese paso nuevo
 * ("en ruta") se registra por su cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('packages')->where('status', 'listo')->update(['status' => 'bodega']);
        DB::table('package_events')->where('status', 'listo')->update(['status' => 'bodega']);
    }

    public function down(): void
    {
        // Lo que estaba en ruta vuelve al paso anterior: antes no existía.
        DB::table('packages')->whereIn('status', ['bodega', 'en_ruta'])->update(['status' => 'listo']);
        DB::table('package_events')->whereIn('status', ['bodega', 'en_ruta'])->update(['status' => 'listo']);
    }
};
