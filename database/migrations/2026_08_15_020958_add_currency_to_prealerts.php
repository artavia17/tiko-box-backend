<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prealerts', function (Blueprint $table) {
            // Moneda en la que el cliente declara el valor de los artículos.
            $table->enum('currency', ['USD', 'CRC'])->default('USD')->after('courier');
        });
    }

    public function down(): void
    {
        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
