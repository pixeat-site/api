<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Force JSON parsing quando Content-Type está ausente
        $middleware->prepend(\App\Http\Middleware\ForceJsonRequest::class);
        // Debug middleware para ver o que chega no Laravel
        $middleware->append(\App\Http\Middleware\DebugRequest::class);
        
        // Registrar middleware customizado
        $middleware->alias([
            'check.analysis.limit' => \App\Http\Middleware\CheckAnalysisLimit::class,
        ]);
        
        // Desabilitar redirecionamento para login na API
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Retornar JSON para erros de autenticação na API
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }
        });
    })->create();
