<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movement_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_movement_id')
                ->constrained('cash_movements')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('type', 255)->comment('PAGADO, DEUDA');
            $table->timestamp('due_at')->nullable()->comment('Cancelar en la fecha');
            $table->timestamp('paid_at')->nullable()->comment('Pagado en la fecha');
            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->onUpdate('restrict')
                ->onDelete('restrict');
            $table->string('payment_method', 255);
            $table->string('number', 255);
            $table->foreignId('card_id')
                ->nullable()
                ->constrained('cards')
                ->onUpdate('restrict')
                ->onDelete('restrict');
            $table->string('card', 255);
            $table->foreignId('bank_id')
                ->nullable()
                ->constrained('banks')
                ->onUpdate('restrict')
                ->onDelete('restrict');
            $table->string('bank', 255);
            $table->foreignId('digital_wallet_id')
                ->nullable()
                ->constrained('digital_wallets')
                ->onUpdate('restrict')
                ->onDelete('restrict');
            $table->string('digital_wallet', 255);
            $table->foreignId('payment_gateway_id')
                ->nullable()
                ->constrained('payment_gateways')
                ->onUpdate('restrict')
                ->onDelete('restrict');
            $table->string('payment_gateway', 255);
            $table->decimal('amount', 24, 6);
            $table->text('comment');
            $table->string('status', 1)->default('A');
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movement_details');
    }
};
