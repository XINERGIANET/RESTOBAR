<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_movement_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_movement_id')
                ->constrained('order_movements')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('code');
            $table->string('description');
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products');
            $table->json('product_snapshot')->nullable();
            $table->foreignId('unit_id')->constrained('units');
            $table->foreignId('tax_rate_id')
                ->nullable()
                ->constrained('tax_rates');
            $table->json('tax_rate_snapshot')->nullable();
            $table->decimal('quantity', 24, 6);
            $table->decimal('amount', 24, 6);
            $table->text('comment')->nullable();
            $table->foreignId('parent_detail_id')
                ->nullable()
                ->constrained('order_movement_details');
            $table->json('complements')->default(json_encode([]));
            $table->string('status', 1)->default('A');
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_movement_details');
    }
};
