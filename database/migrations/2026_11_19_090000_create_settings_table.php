<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes que administración puede cambiar sin tocar el código.
 *
 * Clave y valor sueltos a propósito: lo que entra acá son textos y números
 * del negocio, y una columna por cada uno obligaría a una migración cada
 * vez que aparece otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
