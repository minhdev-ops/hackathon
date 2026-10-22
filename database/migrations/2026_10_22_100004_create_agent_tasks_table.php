<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained('agents');
            $table->foreignUuid('playbook_id')->constrained('playbooks');
            $table->text('instruction');
            $table->string('status')->default('proposing');
            $table->json('proposal')->nullable();
            $table->json('result')->nullable();
            $table->json('log')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_tasks');
    }
};
