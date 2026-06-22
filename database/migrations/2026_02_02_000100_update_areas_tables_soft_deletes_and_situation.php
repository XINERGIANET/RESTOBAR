<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::table('areas')
            ->where('deleted', true)
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn('deleted');
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('situation', 10)->default('libre');
        });

        DB::table('tables')
            ->where('deleted', true)
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn('deleted');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->boolean('deleted')->default(false);
        });

        DB::table('areas')
            ->whereNotNull('deleted_at')
            ->update(['deleted' => true, 'updated_at' => now()]);

        Schema::table('areas', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->boolean('deleted')->default(false);
        });

        DB::table('tables')
            ->whereNotNull('deleted_at')
            ->update(['deleted' => true, 'updated_at' => now()]);

        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn('situation');
            $table->dropSoftDeletes();
        });
    }
};
