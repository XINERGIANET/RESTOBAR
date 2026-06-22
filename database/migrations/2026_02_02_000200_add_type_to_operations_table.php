<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->char('type', 1)->default('R');
        });

        DB::table('operations')
            ->where('name', 'like', 'N%')
            ->update(['type' => 'T']);

        DB::table('operations')
            ->whereNotNull('name')
            ->where('name', 'not like', 'N%')
            ->update(['type' => 'R']);
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
