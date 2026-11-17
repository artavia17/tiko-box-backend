<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte por tickets.
 *
 * Reemplaza el salto a WhatsApp: lo que se habla queda en la plataforma, con
 * su historial y sus adjuntos, y no en el teléfono de quien haya contestado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Quién lo atiende. Nulo mientras nadie lo haya tomado.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Con qué necesita ayuda: sirve para repartir y para priorizar.
            $table->string('category')->index();
            $table->string('subject');
            $table->string('status')->default('abierto')->index();

            // Para ordenar la bandeja por actividad sin contar mensajes.
            $table->timestamp('last_message_at')->nullable()->index();
            // Hasta qué mensaje leyó cada lado. Por id y no por fecha: dos
            // mensajes del mismo segundo son indistinguibles por tiempo y
            // uno de ellos se perdería del contador.
            $table->unsignedBigInteger('customer_read_message_id')->nullable();
            $table->unsignedBigInteger('staff_read_message_id')->nullable();

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            // Quién escribió. El rol sale del usuario, no se copia acá.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'id']);
        });

        Schema::create('support_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_message_id')->constrained()->cascadeOnDelete();

            $table->string('path');
            // El nombre original va aparte: en el disco el archivo queda con
            // un nombre al azar para que no se pueda adivinar.
            $table->string('name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_attachments');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
