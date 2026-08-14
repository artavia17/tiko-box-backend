<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorized_people', function (Blueprint $table) {
            $table->enum('identification_type', ['nacional', 'extranjero'])
                ->default('nacional')
                ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('authorized_people', function (Blueprint $table) {
            $table->dropColumn('identification_type');
        });
    }
};
