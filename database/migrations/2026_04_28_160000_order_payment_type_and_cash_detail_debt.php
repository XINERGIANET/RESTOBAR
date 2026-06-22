<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_movement_details') && Schema::hasColumn('cash_movement_details', 'payment_method_id')) {
            Schema::table('cash_movement_details', function (Blueprint $table) {
                $table->dropForeign(['payment_method_id']);
            });

            Schema::table('cash_movement_details', function (Blueprint $table) {
                $table->unsignedBigInteger('payment_method_id')->nullable()->change();
            });

            Schema::table('cash_movement_details', function (Blueprint $table) {
                $table->foreign('payment_method_id')
                    ->references('id')
                    ->on('payment_methods')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('order_movements')) {
            if (Schema::hasColumn('order_movements', 'sale_payment_type') && ! Schema::hasColumn('order_movements', 'payment_type')) {
                Schema::table('order_movements', function (Blueprint $table) {
                    $table->renameColumn('sale_payment_type', 'payment_type');
                });
            } elseif (! Schema::hasColumn('order_movements', 'payment_type')) {
                Schema::table('order_movements', function (Blueprint $table) {
                    $table->string('payment_type', 20)
                        ->nullable()
                        ->comment('CONTADO o CREDITO — condición de venta al cerrar el pedido');
                });
            }

            Schema::table('order_movements', function (Blueprint $table) {
                if (! Schema::hasColumn('order_movements', 'credit_days')) {
                    $table->unsignedInteger('credit_days')->nullable()->after('payment_type');
                }
                if (! Schema::hasColumn('order_movements', 'debt_due_at')) {
                    $table->timestamp('debt_due_at')->nullable()->after('credit_days');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_movements')) {
            Schema::table('order_movements', function (Blueprint $table) {
                if (Schema::hasColumn('order_movements', 'debt_due_at')) {
                    $table->dropColumn('debt_due_at');
                }
                if (Schema::hasColumn('order_movements', 'credit_days')) {
                    $table->dropColumn('credit_days');
                }
            });

            if (Schema::hasColumn('order_movements', 'payment_type') && ! Schema::hasColumn('order_movements', 'sale_payment_type')) {
                Schema::table('order_movements', function (Blueprint $table) {
                    $table->renameColumn('payment_type', 'sale_payment_type');
                });
            }
        }

        // No revertimos payment_method_id a NOT NULL: podría haber filas DEUDA con NULL.
    }
};
