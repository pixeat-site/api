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
            $table->integer('age')->nullable();
            $table->decimal('height', 5, 2)->nullable(); // Ex: 175.50 cm
            $table->decimal('weight', 5, 2)->nullable(); // Ex: 70.50 kg
            $table->decimal('target_weight', 5, 2)->nullable(); // Ex: 65.00 kg
            $table->integer('activity_level')->default(1); // 0-4
            $table->integer('goal')->default(1); // 0=perder, 1=manter, 2=ganhar
            $table->decimal('daily_calories', 8, 2)->default(2000.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'age', 'height', 'weight', 'target_weight', 
                'activity_level', 'goal', 'daily_calories'
            ]);
        });
    }
};
