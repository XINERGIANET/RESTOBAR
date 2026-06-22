<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_branch', function (Blueprint $table) {
            $table->id();
            $table->string('status', 1)->default('E');
            $table->date('expiration_date')->nullable();
            $table->decimal('stock_minimum', 24, 6);
            $table->decimal('stock_maximum', 24, 6);
            $table->decimal('minimum_sell', 24, 6)->nullable();
            $table->decimal('minimum_purchase', 24, 6)->nullable();
            $table->string('favorite', 1)->default('N');
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->string('unit_sale', 255)->default('N')->comment('Y => Yes, N => No');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('branch_id');
            $table->timestamps();
            $table->softDeletes();
            $table->decimal('duration_minutes', 8, 2)->default(0)->comment('minutes');
            $table->unsignedBigInteger('supplier_id')->nullable();

            $table->foreign('tax_rate_id')
                ->references('id')
                ->on('tax_rates')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('supplier_id')
                ->references('id')
                ->on('people')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_branch');
    }
};
