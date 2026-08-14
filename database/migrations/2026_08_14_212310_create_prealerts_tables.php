<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prealerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_number')->index();
            $table->string('origin')->default('Miami');
            $table->string('courier')->nullable();
            $table->date('expected_arrival')->nullable();
            // Estado del paquete declarado por el cliente.
            $table->enum('status', ['pendiente', 'recibido', 'en_transito', 'entregado'])
                ->default('pendiente');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tracking_number']);
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

    public function down(): void
    {
        Schema::dropIfExists('prealert_items');
        Schema::dropIfExists('prealerts');
    }
};
