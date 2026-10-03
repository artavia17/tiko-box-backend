<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién cobró el paquete.
 *
 * Va como texto y no como cuenta del sistema: muchas veces cobra alguien que
 * no tiene usuario, y lo que se necesita es poder preguntarle a esa persona,
 * no enlazarla con un registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('collected_by')->nullable()->after('void_reason');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('collected_by');
        });
    }
};
