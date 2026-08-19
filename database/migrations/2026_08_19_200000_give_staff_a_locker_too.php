<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El personal también compra: tener permisos de almacén no debería impedir
 * recibir paquetes, así que las cuentas que quedaron sin casillero lo
 * reciben. Quien no lo necesite puede quitárselo desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['empleado', 'admin'])
            ->whereNull('locker_code')
            ->update(['locker_code' => config('tikabox.locker.code')]);
    }

    public function down(): void
    {
        // No se puede saber cuáles tenían casillero antes de esta migración.
    }
};
