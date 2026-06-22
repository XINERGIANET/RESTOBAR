<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('description', 255);
            $table->string('abbreviation', 255);
            $table->string('type', 255)->comment('PRODUCT, COMPONENT');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('base_unit_id');
            $table->string('kardex', 1)->default('N');
            $table->string('is_compound', 1)->default('N');
            $table->text('image')->nullable();
            $table->string('complement', 255)->default('NO')->comment('NO => No allows complements, HAS => Has complements, IS => Is complement');
            $table->string('complement_mode', 255)->default('')->comment('ALL => Same complements are free, QUANTITY => Complement quantity is free');
            $table->string('classification', 255)->default('GOOD')->comment('GOOD, SERVICE');
            $table->text('features')->default('');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onUpdate('restrict')
                ->onDelete('restrict');
            $table->foreign('base_unit_id')
                ->references('id')
                ->on('units')
                ->onUpdate('restrict')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
