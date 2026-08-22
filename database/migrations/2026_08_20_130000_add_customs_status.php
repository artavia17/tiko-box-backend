<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aduanas es un paso más del recorrido, y no será el último que pidan: los
 * estados pasan de enum a texto para que agregar uno no exija tocar el
 * esquema. La lista válida vive en la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE packages MODIFY status VARCHAR(20) NOT NULL DEFAULT 'recibido'");
        DB::statement('ALTER TABLE package_events MODIFY status VARCHAR(20) NOT NULL');
    }

    public function down(): void
    {
        // Los paquetes en aduanas no caben en el enum viejo.
        DB::table('packages')->where('status', 'aduanas')->update(['status' => 'en_transito']);
        DB::table('package_events')->where('status', 'aduanas')->update(['status' => 'en_transito']);

        DB::statement("ALTER TABLE packages MODIFY status ENUM('recibido','en_transito','listo','entregado') NOT NULL DEFAULT 'recibido'");
        DB::statement("ALTER TABLE package_events MODIFY status ENUM('recibido','en_transito','listo','entregado') NOT NULL");
    }
};
