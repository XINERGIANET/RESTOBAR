<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_promotion_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_group_id')->constrained('product_promotion_groups')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('default_quantity', 24, 6)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['promotion_group_id', 'product_id'], 'promotion_group_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_promotion_group_items');
    }
};
