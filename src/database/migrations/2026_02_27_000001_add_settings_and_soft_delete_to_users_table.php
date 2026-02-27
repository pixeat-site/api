<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Story 1.1: configurações do usuário (settings) e soft delete para exclusão de conta.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('dark_mode')->default(false)->after('daily_calories');
            $table->boolean('notifications_enabled')->default(true)->after('dark_mode');
            $table->string('language', 10)->default('pt')->after('notifications_enabled');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dark_mode', 'notifications_enabled', 'language']);
            $table->dropSoftDeletes();
        });
    }
};
