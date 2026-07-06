<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_promotion')
                ->default(false)
                ->after('recipe');
            $table->boolean('promotion_mix_and_match')
                ->default(false)
                ->after('is_promotion');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_promotion',
                'promotion_mix_and_match',
            ]);
        });
    }
};
