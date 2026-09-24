<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aduanas es un paso más del recorrido, y no será el último que pidan: los
 * estados pasan de enum a texto para que agregar uno no exija tocar el
 * esquema. La lista válida vive en la aplicación.
 *
 * Va por el constructor de esquemas y no por SQL crudo: el `MODIFY` de MySQL
 * dejaba la suite de pruebas sin poder levantar el esquema en SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('status', 20)->default('recibido')->change();
        });

        Schema::table('package_events', function (Blueprint $table) {
            $table->string('status', 20)->change();
        });
    }

    public function down(): void
    {
        // Los paquetes en aduanas no caben en el enum viejo.
        DB::table('packages')->where('status', 'aduanas')->update(['status' => 'en_transito']);
        DB::table('package_events')->where('status', 'aduanas')->update(['status' => 'en_transito']);

        $old = ['recibido', 'en_transito', 'listo', 'entregado'];

        Schema::table('packages', function (Blueprint $table) use ($old) {
            $table->enum('status', $old)->default('recibido')->change();
        });

        Schema::table('package_events', function (Blueprint $table) use ($old) {
            $table->enum('status', $old)->change();
        });
    }
};
