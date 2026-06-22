<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('recipe')
                  ->default(false)
                  ->after('name')
                  ->comment('1=Producto manufacturado (Tiene receta), 0=Producto estándar');
        });

        if (Schema::hasColumn('product_branch', 'recipe')) {
            Schema::table('product_branch', function (Blueprint $table) {
                $table->dropColumn('recipe');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('recipe');
        });

        Schema::table('product_branch', function (Blueprint $table) {
             $table->enum('recipe', ['S', 'N'])->default('N');
        });
    }
};