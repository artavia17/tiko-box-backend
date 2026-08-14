<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Genera el código de casillero de forma incremental: SJO0024956, SJO0024957...
 *
 * Se calcula a partir del máximo código existente y se bloquea la tabla dentro
 * de la transacción del registro, para que dos registros simultáneos no
 * obtengan el mismo número.
 */
class LockerCodeGenerator
{
    public function next(): string
    {
        $prefix = (string) config('tikabox.locker.prefix');
        $padding = (int) config('tikabox.locker.padding');
        $start = (int) config('tikabox.locker.start');

        $highest = User::query()
            ->where('locker_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->select(DB::raw(sprintf(
                'MAX(CAST(SUBSTRING(locker_code, %d) AS UNSIGNED)) as sequence',
                strlen($prefix) + 1,
            )))
            ->value('sequence');

        $next = max((int) $highest, $start) + 1;

        return $prefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }
}
