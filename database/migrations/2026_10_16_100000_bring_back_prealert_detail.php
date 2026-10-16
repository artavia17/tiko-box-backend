<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La prealerta vuelve a declarar contenido, transportista y retención.
 *
 * En agosto se habían quitado los artículos con el criterio de que la factura
 * adjunta bastaba. Aduana no los usa igual: el valor declarado línea por línea
 * es lo que permite calcular impuestos sin aforar el paquete a ojo, y una
 * factura escaneada no es un dato que el sistema pueda leer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prealerts', function (Blueprint $table) {
            // Quién lo trae a la bodega. Sale del catálogo que ya administra
            // el almacén, así que se guarda el nombre y no un id.
            $table->string('courier', 60)->nullable()->after('tracking_number');

            // Retener: no despachar desde Miami hasta nuevo aviso.
            $table->boolean('is_retained')->default(false)->after('expected_arrival');
            $table->string('retention_note', 500)->nullable()->after('is_retained');
        });

        Schema::create('prealert_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prealert_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('description', 200);
            // Valor unitario en dólares, que es lo que declara aduana.
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prealert_items');

        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropColumn(['courier', 'is_retained', 'retention_note']);
        });
    }
};
