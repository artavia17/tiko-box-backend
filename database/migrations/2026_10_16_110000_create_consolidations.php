<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidaciones: agrupar de 2 a 10 paquetes del mismo cliente para
 * procesarlos y cobrarlos como uno solo.
 *
 * Solo se guardan los hechos: cuándo se activó, cuándo se pidió el envío y
 * cuándo se procesó. El estado que ve el cliente se calcula a partir de esas
 * fechas, así nunca queda una fila diciendo "Activo" porque nadie corrió el
 * proceso que debía vencerla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consolidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Arranca cuando llega el primer paquete, no cuando se crea.
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            // El paquete que representa a todo el consolidado, al cerrarlo.
            $table->foreignId('generated_package_id')->nullable()
                ->constrained('packages')->nullOnDelete();

            $table->timestamps();
        });

        Schema::table('prealerts', function (Blueprint $table) {
            $table->foreignId('consolidation_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consolidation_id');
        });

        Schema::dropIfExists('consolidations');
    }
};
