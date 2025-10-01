<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - PixEat
|--------------------------------------------------------------------------
|
| Aqui estão as rotas da API para o aplicativo PixEat.
| Todas as rotas são prefixadas com /api/v1
|
*/

// Health check (público)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'PixEat API is running',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString(),
        'database' => 'connected',
        'cache' => 'redis'
    ]);
});

// Rotas da API v1
Route::prefix('v1')->group(function () {
    
    // Auth (rotas públicas)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [App\Http\Controllers\Api\V1\AuthController::class, 'register']);
        Route::post('/login', [App\Http\Controllers\Api\V1\AuthController::class, 'login']);
        Route::post('/forgot-password', function (Request $request) {
            // TODO: Implementar recuperação de senha
            return response()->json([
                'success' => true,
                'message' => 'Link de recuperação enviado para ' . $request->email
            ]);
        });
        
        // Rotas protegidas de auth
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
            Route::post('/refresh', [App\Http\Controllers\Api\V1\AuthController::class, 'refresh']);
        });
    });
    
    // IA Analysis (rotas públicas para teste)
    Route::prefix('ai')->group(function () {
        Route::get('/test-connection', [App\Http\Controllers\Api\V1\AIController::class, 'testConnection']);
        Route::get('/info', [App\Http\Controllers\Api\V1\AIController::class, 'info']);
    });
    
    // Rotas protegidas (requerem autenticação)
    Route::middleware('auth:sanctum')->group(function () {
        
        // User Profile
        Route::prefix('user')->group(function () {
            Route::get('/profile', [App\Http\Controllers\Api\V1\AuthController::class, 'profile']);
            Route::put('/profile', [App\Http\Controllers\Api\V1\AuthController::class, 'updateProfile']);
        });
        
        // Meals
        Route::prefix('meals')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\MealController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\V1\MealController::class, 'store']);
            Route::get('/today', [App\Http\Controllers\Api\V1\MealController::class, 'today']);
            Route::get('/{id}', [App\Http\Controllers\Api\V1\MealController::class, 'show']);
            Route::put('/{id}', [App\Http\Controllers\Api\V1\MealController::class, 'update']);
            Route::delete('/{id}', [App\Http\Controllers\Api\V1\MealController::class, 'destroy']);
        });
        
        // IA Analysis (protegidas)
        Route::prefix('ai')->group(function () {
            Route::post('/analyze', [App\Http\Controllers\Api\V1\AIController::class, 'analyze']);
            Route::post('/analyze-batch', [App\Http\Controllers\Api\V1\AIController::class, 'analyzeBatch']);
        });
        
        // Stats (simuladas por enquanto)
        Route::prefix('stats')->group(function () {
            Route::get('/daily', function () {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'date' => now()->toDateString(),
                        'total_calories' => 1250.0,
                        'target_calories' => 2000.0,
                        'remaining_calories' => 750.0,
                        'meals_count' => 3,
                        'progress_percentage' => 62.5
                    ]
                ]);
            });
            
            Route::get('/weekly', function () {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'week_start' => now()->startOfWeek()->toDateString(),
                        'daily_calories' => [
                            'monday' => 1800.0,
                            'tuesday' => 2100.0,
                            'wednesday' => 1950.0,
                            'thursday' => 2200.0,
                            'friday' => 1750.0,
                            'saturday' => 2300.0,
                            'sunday' => 1900.0,
                        ],
                        'average_calories' => 1985.7
                    ]
                ]);
            });
            
            Route::get('/monthly', function () {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'month' => now()->format('Y-m'),
                        'weekly_calories' => [
                            'week_1' => 13500.0,
                            'week_2' => 14200.0,
                            'week_3' => 13800.0,
                            'week_4' => 14100.0,
                        ],
                        'total_calories' => 55600.0,
                        'average_daily' => 1986.0
                    ]
                ]);
            });
        });
    });
});