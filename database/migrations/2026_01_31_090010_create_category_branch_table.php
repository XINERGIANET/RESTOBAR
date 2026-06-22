<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_branch', function (Blueprint $table) {
            $table->id();
            $table->string('menu_type', 255)->comment('PLATOS A LA CARTA');
            $table->string('status', 1)->default('E');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('branch_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_branch');
    }
};
