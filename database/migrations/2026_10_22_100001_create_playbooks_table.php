<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playbooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('required_doc_types')->nullable();
            $table->json('optional_doc_types')->nullable();
            $table->json('forbidden_doc_types')->nullable();
            $table->string('recipient_label');
            $table->unsignedSmallInteger('default_ttl_minutes');
            $table->unsignedSmallInteger('max_ttl_minutes');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playbooks');
    }
};
