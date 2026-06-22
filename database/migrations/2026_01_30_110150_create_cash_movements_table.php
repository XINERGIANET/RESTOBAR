<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_concept_id')->constrained('payment_concepts')->onUpdate('restrict')->onDelete('restrict');
            $table->string('currency', 255)->default('PEN');
            $table->decimal('exchange_rate', 8, 3);
            $table->decimal('total', 8, 2);
            $table->foreignId('cash_register_id')->constrained('cash_registers');
            $table->string('cash_register', 255);
            $table->foreignId('shift_id')->constrained('shifts');
            $table->json('shift_snapshot');
            $table->foreignId('movement_id')->constrained('movements')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
