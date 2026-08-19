<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // cliente: quien recibe paquetes. empleado: almacén. admin: todo.
            $table->enum('role', ['cliente', 'empleado', 'admin'])
                ->default('cliente')
                ->after('id')
                ->index();
        });

        // El personal no tiene casillero ni dirección de entrega.
        Schema::table('users', function (Blueprint $table) {
            $table->string('locker_code')->nullable()->change();
            $table->string('identification')->nullable()->change();
            $table->string('phone', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
