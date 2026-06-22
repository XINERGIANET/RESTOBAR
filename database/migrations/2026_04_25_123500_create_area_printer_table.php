<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_printer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('printer_id')->constrained('printers_branch')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['area_id', 'printer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_printer');
    }
};
