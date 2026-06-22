<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_movements')) {
            return;
        }

        Schema::table('sales_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_movements', 'account_receivable_payable_id')) {
                $table->foreignId('account_receivable_payable_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('account_receivable_payables')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('sales_movements', 'credit_days')) {
                $table->unsignedInteger('credit_days')->nullable()->after('account_receivable_payable_id');
            }
            if (! Schema::hasColumn('sales_movements', 'debt_due_at')) {
                $table->timestamp('debt_due_at')->nullable()->after('credit_days');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_movements')) {
            return;
        }

        Schema::table('sales_movements', function (Blueprint $table) {
            if (Schema::hasColumn('sales_movements', 'debt_due_at')) {
                $table->dropColumn('debt_due_at');
            }
            if (Schema::hasColumn('sales_movements', 'credit_days')) {
                $table->dropColumn('credit_days');
            }
            if (Schema::hasColumn('sales_movements', 'account_receivable_payable_id')) {
                $table->dropForeign(['account_receivable_payable_id']);
                $table->dropColumn('account_receivable_payable_id');
            }
        });
    }
};
