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
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('food_name');
            $table->decimal('calories', 8, 2);
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snack']);
            $table->timestamp('consumed_at');
            $table->json('ingredients')->nullable();
            $table->text('description')->nullable();
            $table->decimal('confidence', 3, 2)->nullable(); // 0.00 a 1.00
            $table->string('image_path')->nullable();
            $table->timestamps();
            
            // Índices para performance
            $table->index(['user_id', 'consumed_at']);
            $table->index(['user_id', 'meal_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
