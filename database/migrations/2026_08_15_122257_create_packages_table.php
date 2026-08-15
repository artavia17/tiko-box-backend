<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            // Dueño del paquete y quien lo registró en el almacén.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('prealert_id')->nullable()->constrained()->nullOnDelete();

            $table->string('tracking_number')->index();
            $table->string('courier')->nullable();
            $table->string('store')->nullable();
            $table->text('description')->nullable();

            // Peso facturado y lo que se le cobra al cliente.
            $table->decimal('weight_lb', 8, 2);
            $table->decimal('price_per_pound', 8, 2);
            $table->decimal('total', 10, 2);

            $table->enum('status', ['recibido', 'en_transito', 'listo', 'entregado'])
                ->default('recibido')
                ->index();

            $table->timestamp('received_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tracking_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
