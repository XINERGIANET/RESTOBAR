<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_payment_method', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_gateway_id')
                ->constrained('payment_gateways')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_payment_method');
    }
};
