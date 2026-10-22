<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tide_sub')->nullable()->unique()->after('role');
            $table->string('vault_kdf_salt')->nullable()->after('tide_sub');
            $table->index('tide_sub');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tide_sub']);
            $table->dropColumn(['tide_sub', 'vault_kdf_salt']);
        });
    }
};
