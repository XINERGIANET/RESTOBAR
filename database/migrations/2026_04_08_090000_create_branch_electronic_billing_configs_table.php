<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_electronic_billing_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('provider')->default('apisunat');
            $table->boolean('enabled')->default(false);
            $table->string('api_url')->nullable();
            $table->string('persona_id')->nullable();
            $table->string('persona_token')->nullable();
            $table->string('series_boleta', 8)->default('B001');
            $table->string('series_factura', 8)->default('F001');
            $table->timestamps();

            $table->unique('branch_id', 'branch_electronic_billing_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_electronic_billing_configs');
    }
};
