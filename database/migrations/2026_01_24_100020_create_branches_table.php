<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('tax_id');
            $table->string('ruc')->nullable();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('legal_name');
            $table->string('logo')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('location_id')->constrained('locations');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};