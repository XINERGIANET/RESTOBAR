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
        Schema::create('account_receivable_payables', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['RECEIVABLE', 'PAYABLE'])->default('RECEIVABLE');
            $table->foreignId('person_id')->constrained('people');
            $table->foreignId('movement_id')->nullable()->constrained('movements');
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamp('due_at')->nullable();
            $table->enum('status', ['NUEVO', 'PAGANDO', 'CANCELADO'])->default('NUEVO');
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
        Schema::dropIfExists('account_receivable_payables');
    }
};
