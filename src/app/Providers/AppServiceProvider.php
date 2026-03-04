<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AnalysisImageService;
use App\Services\GeminiAIService;
use App\Services\GroqAIService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GeminiAIService::class, function ($app) {
            return new GeminiAIService();
        });
        $this->app->singleton(GroqAIService::class, function ($app) {
            return new GroqAIService();
        });
        $this->app->singleton(AnalysisImageService::class, function ($app) {
            return new AnalysisImageService();
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