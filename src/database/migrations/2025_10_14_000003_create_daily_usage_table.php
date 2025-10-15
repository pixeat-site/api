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
        Schema::create('daily_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('usage_date'); // Data do uso
            $table->integer('analyses_count')->default(0); // Quantidade de análises usadas
            $table->timestamps();
            
            // Índice único para evitar duplicatas
            $table->unique(['user_id', 'usage_date']);
            
            // Índices para performance
            $table->index(['user_id', 'usage_date']);
            $table->index('usage_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_usage');
    }
};
