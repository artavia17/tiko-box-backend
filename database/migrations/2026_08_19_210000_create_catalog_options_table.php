<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listas que administra el negocio: transportistas y tiendas.
 *
 * Van en una sola tabla porque se comportan igual; el tipo las separa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_options', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['carrier', 'store']);
            $table->string('name');
            $table->timestamps();

            $table->unique(['type', 'name']);
            $table->index(['type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_options');
    }
};
