<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            $table->string('second_last_name')->nullable()->after('last_name');
            $table->string('identification')->unique()->after('second_last_name');
            $table->string('phone', 30)->after('identification');
            $table->string('locker_code')->unique()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'second_last_name',
                'identification',
                'phone',
                'locker_code',
            ]);
        });
    }
};
