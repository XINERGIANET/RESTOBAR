<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->string('printer_name', 120)->index();
            $table->string('kind', 40)->default('comanda')->index();
            $table->longText('payload_base64');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'printer_name', 'status', 'created_at'], 'print_jobs_bridge_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
