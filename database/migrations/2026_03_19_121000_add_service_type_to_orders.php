<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('order_movements', 'service_type')) {
                $table->string('service_type')->default('IN_SITU')->comment('IN_SITU, TAKE_AWAY, DELIVERY');
            }
        });

        DB::table('sales_movements')
            ->whereIn('sale_type', ['RETAIL', 'MINORISTA', 'POS'])
            ->update(['sale_type' => 'IN_SITU']);
    }

    public function down(): void
    {
        Schema::table('order_movements', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
