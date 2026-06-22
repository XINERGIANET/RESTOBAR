<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_movements', function (Blueprint $table) {
            $table->id();
            $table->string('currency')->default('PEN');
            $table->decimal('exchange_rate', 8, 3)->default(1);
            $table->decimal('subtotal', 24, 6)->default(0);
            $table->decimal('tax', 24, 6)->default(0);
            $table->decimal('total', 24, 6)->comment('subtotal + tax');
            $table->unsignedInteger('people_count')->default(1)->comment('Número de personas');
            $table->timestamp('finished_at')->nullable()->comment('Order end time');
            $table->foreignId('table_id')->nullable()->constrained('tables')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('area_id')->nullable()->constrained('areas')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('delivery_amount', 24, 6)->default(0)->comment('Delivery fee');
            $table->string('contact_phone', 255)->nullable();
            $table->string('delivery_address', 255)->nullable();
            $table->timestamp('delivery_time')->nullable();
            $table->string('status')->default('PENDIENTE')->comment('PENDIENTE, FINALIZADO, CANCELADO');
            $table->foreignId('movement_id')->constrained('movements')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_movements');
    }
};
