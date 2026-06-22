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
        Schema::create('product_branch_printer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_branch_id')->constrained('product_branch');
            $table->foreignId('printer_id')->constrained('printers_branch');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_branch_printer');
    }
};
