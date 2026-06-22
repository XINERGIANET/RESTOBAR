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
        Schema::table('profiles', function (Blueprint $table) {
            $table->foreignId('default_view_id')
                ->nullable()
                ->after('id')
                ->constrained('views')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE profiles DROP CONSTRAINT IF EXISTS profiles_default_view_id_foreign');
        }
        Schema::table('profiles', function (Blueprint $table) {
            if (Schema::hasColumn('profiles', 'default_view_id')) {
                $table->dropColumn('default_view_id');
            }
        });
    }
};
