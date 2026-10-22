<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('doc_type');
            $table->text('title_ct');
            $table->string('ciphertext_path');
            $table->text('iv');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->string('enc_mode')->default('fallback');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['owner_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
