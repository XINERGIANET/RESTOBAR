<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('person_type');
            $table->string('phone');
            $table->string('email');
            $table->string('document_number');
            $table->string('address');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('branch_id')->constrained('branches');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};