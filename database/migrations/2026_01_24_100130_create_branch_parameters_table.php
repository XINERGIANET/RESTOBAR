<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_parameters', function (Blueprint $table) {
            $table->id();
            $table->text('value');
            $table->foreignId('parameter_id')->constrained('parameters');
            $table->foreignId('branch_id')->constrained('branches');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_parameters');
    }
};