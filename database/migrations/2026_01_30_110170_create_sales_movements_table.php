<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_movements', function (Blueprint $table) {
            $table->id();
            $table->json('branch_snapshot');
            $table->string('series');
            $table->string('year');
            $table->string('detail_type')->default('DETAILED')->comment('DETAILED, SUMMARY');
            $table->string('consumption', 1)->default('N')->comment('S => Sale by consumption, N => Detailed sale');
            $table->string('payment_type')->default('CASH')->comment('CASH, CREDIT');
            $table->string('status')->default('')->comment('Status vs tax authority');
            $table->string('sale_type')->default('RETAIL')->comment('RETAIL, WHOLESALE');
            $table->string('currency')->default('PEN');
            $table->decimal('exchange_rate', 8, 3);
            $table->decimal('subtotal', 8, 2);
            $table->decimal('tax', 8, 2);
            $table->decimal('total', 8, 2)->comment('subtotal + tax');
            $table->foreignId('movement_id')->constrained('movements')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_movements');
    }
};
