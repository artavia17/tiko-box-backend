<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La prealerta se reduce a lo que el cliente necesita declarar: el número de
 * rastreo, la factura de la compra y cuándo espera recibirla. El detalle de
 * artículos y montos lo reemplaza la factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prealerts', function (Blueprint $table) {
            // Nullable por las prealertas que se crearon antes de pedir factura.
            $table->string('invoice_path')->nullable()->after('tracking_number');
            $table->dropColumn(['courier', 'currency', 'notes']);
        });

        Schema::dropIfExists('prealert_items');
    }

    public function down(): void
    {
        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropColumn('invoice_path');
            $table->string('courier')->nullable()->after('origin');
            $table->string('currency', 3)->default('USD')->after('courier');
            $table->text('notes')->nullable()->after('status');
        });

        Schema::create('prealert_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prealert_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('description');
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });
    }
};
