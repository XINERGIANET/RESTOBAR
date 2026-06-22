<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_movements', function (Blueprint $table) {
            $table->decimal('takeaway_disposable_amount', 24, 6)->default(0)->after('delivery_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_movements', function (Blueprint $table) {
            $table->dropColumn('takeaway_disposable_amount');
        });
    }
};
