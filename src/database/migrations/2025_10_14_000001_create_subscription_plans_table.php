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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 'free', 'premium'
            $table->string('display_name'); // 'Gratuito', 'Premium'
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2)->default(0); // Preço em reais
            $table->string('stripe_price_id')->nullable(); // ID do preço no Stripe
            $table->integer('daily_analyses_limit'); // Limite de análises por dia
            $table->integer('history_days_limit')->nullable(); // Limite de histórico (null = ilimitado)
            $table->json('features')->nullable(); // Features extras em JSON
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
