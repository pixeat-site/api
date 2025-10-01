<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\GeminiAIService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar o serviço Gemini AI
        $this->app->singleton(GeminiAIService::class, function ($app) {
            return new GeminiAIService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}