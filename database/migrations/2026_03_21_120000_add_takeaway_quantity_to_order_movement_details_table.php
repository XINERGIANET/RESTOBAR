<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_movement_details', function (Blueprint $table) {
            $table->decimal('takeaway_quantity', 24, 6)->default(0)->after('courtesy_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_movement_details', function (Blueprint $table) {
            $table->dropColumn('takeaway_quantity');
        });
    }
};
