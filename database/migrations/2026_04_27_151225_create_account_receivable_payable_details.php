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
        Schema::create('account_receivable_payable_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_receivable_payable_id')->constrained('account_receivable_payables')->onDelete('cascade');
            $table->foreignId('movement_id')->nullable()->constrained('movements');
            $table->decimal('amount', 15, 2)->default(0);
            $table->foreignId('branch_id')->constrained('branches');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_receivable_payable_details');
    }
};
