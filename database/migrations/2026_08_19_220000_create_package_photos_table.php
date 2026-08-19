<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Varias fotos por paquete: una caja se documenta desde distintos ángulos,
 * y una sola no alcanza cuando llega golpeada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();

            $table->index('package_id');
        });

        // Las fotos que ya existían pasan a ser la primera de su paquete.
        DB::table('packages')
            ->whereNotNull('photo_path')
            ->orderBy('id')
            ->each(function ($package) {
                DB::table('package_photos')->insert([
                    'package_id' => $package->id,
                    'path' => $package->photo_path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('description');
        });

        Schema::dropIfExists('package_photos');
    }
};
