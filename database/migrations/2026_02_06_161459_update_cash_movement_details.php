<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cash_movement_details', function (Blueprint $table) {
            // Modificamos las columnas para que acepten NULL (nullable)
            $table->string('card', 255)->nullable()->change();
            $table->string('bank', 255)->nullable()->change();
            $table->string('digital_wallet', 255)->nullable()->change();
            $table->string('payment_gateway', 255)->nullable()->change();
            $table->string('number', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_movement_details', function (Blueprint $table) {
            $table->string('card', 255)->nullable(false)->change();
            $table->string('bank', 255)->nullable(false)->change();
            $table->string('digital_wallet', 255)->nullable(false)->change();
            $table->string('payment_gateway', 255)->nullable(false)->change();
            $table->string('number', 255)->nullable(false)->change();
        });
    }
};