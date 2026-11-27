<?php

use App\Models\CatalogOption;
use Illuminate\Database\Migrations\Migration;

/**
 * Las formas de pago pasan a ser una lista administrable.
 *
 * Se siembran las que ya estaban escritas en el código: si la lista quedara
 * vacía, nadie podría registrar un cobro hasta acordarse de crearlas.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (CatalogOption::DEFAULT_PAYMENT_METHODS as $name) {
            CatalogOption::firstOrCreate(['type' => 'payment_method', 'name' => $name]);
        }
    }

    public function down(): void
    {
        CatalogOption::where('type', 'payment_method')->delete();
    }
};
