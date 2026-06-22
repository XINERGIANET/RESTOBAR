<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_branch', function (Blueprint $table) {
            $table->foreignId('profile_id')->constrained('profiles');
            $table->foreignId('branch_id')->constrained('branches');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_branch');
    }
};