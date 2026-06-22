<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payment_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_movement_id')->constrained('order_movements')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('mode', 20)->comment('products|amount');
            $table->decimal('subtotal', 24, 6)->default(0);
            $table->decimal('tax', 24, 6)->default(0);
            $table->decimal('total', 24, 6)->default(0);
            $table->string('status', 20)->default('COMPLETED')->comment('COMPLETED|ERROR');
            $table->foreignId('movement_id')->nullable()->constrained('movements')->nullOnDelete()->cascadeOnUpdate();
            $table->string('electronic_invoice_status', 30)->nullable();
            $table->timestamps();

            $table->index(['order_movement_id', 'sequence']);
        });

        Schema::create('order_payment_split_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_payment_split_id')->constrained('order_payment_splits')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('order_movement_detail_id')->constrained('order_movement_details')->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('quantity', 24, 6);
            $table->decimal('amount', 24, 6);
            $table->json('tax_rate_snapshot')->nullable();
            $table->json('product_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::table('order_movements', function (Blueprint $table) {
            $table->string('split_mode', 20)->nullable()->after('status')->comment('products|amount when split started');
            $table->boolean('split_locked_to_amount')->default(false)->after('split_mode');
        });
    }

    public function down(): void
    {
        Schema::table('order_movements', function (Blueprint $table) {
            $table->dropColumn(['split_mode', 'split_locked_to_amount']);
        });
        Schema::dropIfExists('order_payment_split_details');
        Schema::dropIfExists('order_payment_splits');
    }
};
