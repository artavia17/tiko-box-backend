<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El casillero es la suite que Tikabox tiene en el almacén de Miami, la misma
 * para toda la operación, así que el código deja de ser único por cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_locker_code_unique');
            $table->index('locker_code');
        });

        DB::table('users')
            ->whereNotNull('locker_code')
            ->update(['locker_code' => config('tikabox.locker.code')]);
    }

    public function down(): void
    {
        // Los códigos por cliente no se pueden reconstruir: al volver atrás se
        // numeran de nuevo desde el código base para no chocar con el unique.
        $base = (int) preg_replace('/\D/', '', (string) config('tikabox.locker.code'));
        $prefix = preg_replace('/\d/', '', (string) config('tikabox.locker.code'));

        foreach (DB::table('users')->whereNotNull('locker_code')->orderBy('id')->pluck('id') as $index => $id) {
            DB::table('users')->where('id', $id)->update([
                'locker_code' => $prefix.str_pad((string) ($base + $index), 7, '0', STR_PAD_LEFT),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['locker_code']);
            $table->unique('locker_code');
        });
    }
};
