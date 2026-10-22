<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_request_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('change_request_id')->constrained('change_requests')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users');
            $table->enum('decision', ['approve', 'reject']);
            $table->string('signature')->nullable();
            $table->timestamps();

            $table->unique(['change_request_id', 'admin_id']);
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_approvals');
    }
};
