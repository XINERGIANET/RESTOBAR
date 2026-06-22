<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movements', function (Blueprint $table) {
            $table->id();
            $table->string('number', 255);
            $table->timestamp('moved_at');
            $table->foreignId('user_id')->constrained('users');
            $table->string('user_name', 255);
            $table->foreignId('person_id')->nullable()->constrained('people');
            $table->string('person_name', 255);
            $table->foreignId('responsible_id')->constrained('users');
            $table->string('responsible_name', 255);
            $table->text('comment');
            $table->string('status', 1)->default('A');
            $table->foreignId('movement_type_id')->constrained('movement_types')->onUpdate('restrict')->onDelete('restrict');
            $table->foreignId('document_type_id')->constrained('document_types')->onUpdate('restrict')->onDelete('restrict');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('parent_movement_id')->nullable()->constrained('movements')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movements');
    }
};
