<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('branches', 'tax_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('tax_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('branches', 'tax_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('tax_id')->nullable()->after('id');
            });
        }
    }
};

