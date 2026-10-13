<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién del personal cobra paquetes.
 *
 * No se puede deducir del rol: hay administradores que cobran y otros que
 * solo miran los números. Se marca a mano desde Usuarios y permisos, que es
 * donde el negocio decide quién hace qué.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('collects_payments')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('collects_payments');
        });
    }
};
