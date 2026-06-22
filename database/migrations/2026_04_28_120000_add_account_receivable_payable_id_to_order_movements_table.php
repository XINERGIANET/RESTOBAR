<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_movements', function (Blueprint $table) {
            $table->foreignId('account_receivable_payable_id')
                ->nullable()
                ->constrained('account_receivable_payables')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_movements', function (Blueprint $table) {
            $table->dropForeign(['account_receivable_payable_id']);
        });
    }
};
