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
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');
            $table->foreignId('yield_unit_id')->constrained('units')->onDelete('restrict');            
            $table->integer('preparation_time')->nullable(); 
            $table->string('preparation_method', 50)->nullable();
            $table->decimal('yield_quantity', 10, 2)->default(1);
            $table->decimal('cost_total', 10, 2)->default(0);
            $table->string('status', 1)->default('A'); 
            $table->string('image')->nullable();
            $table->text('notes')->nullable();            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
