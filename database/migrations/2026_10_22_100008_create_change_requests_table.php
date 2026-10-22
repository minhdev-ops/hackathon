<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->json('payload');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('required_approvals')->default(2);
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('requested_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
