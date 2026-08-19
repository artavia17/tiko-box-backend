<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada paso del paquete queda registrado: es lo que el cliente ve como
 * seguimiento y lo que permite saber quién movió qué en el almacén.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['recibido', 'en_transito', 'listo', 'entregado']);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['package_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_events');
    }
};
