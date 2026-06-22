<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            
            $table->foreignId('product_id')
                  ->after('id')
                  ->constrained('products')
                  ->onDelete('cascade'); 
            $table->foreignId('company_id')
                  ->after('product_id')
                  ->constrained('companies') 
                  ->onDelete('cascade');
            $table->foreignId('branch_id')
                  ->nullable()
                  ->after('company_id')
                  ->constrained('branches') 
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['company_id']);
            $table->dropForeign(['branch_id']);            
            $table->dropColumn(['product_id', 'company_id', 'branch_id']);
        });
    }
};