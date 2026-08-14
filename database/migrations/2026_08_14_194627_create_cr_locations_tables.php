<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('cantons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('code');
            $table->string('name');
            $table->timestamps();

            $table->unique(['province_id', 'code']);
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canton_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('code');
            $table->string('name');
            $table->timestamps();

            $table->unique(['canton_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cantons');
        Schema::dropIfExists('provinces');
    }
};
