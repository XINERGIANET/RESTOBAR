<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permission', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->foreignId('profile_id')->constrained('profiles');
            $table->foreignId('menu_option_id')->constrained('menu_option');
            $table->foreignId('branch_id')->constrained('branches');
            $table->boolean('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission');
    }
};