<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GeminiAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class AIController extends Controller
{
    private GeminiAIService $geminiService;

    public function __construct(GeminiAIService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Analisa uma imagem de comida usando IA
     */
    public function analyze(Request $request): JsonResponse
    {
        try {
            // Validar a requisição
            $validator = Validator::make($request->all(), [
                'image' => 'required|image|mimes:jpeg,png,jpg|max:10240', // Max 10MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Processar a imagem
            $image = $request->file('image');
            $imageBase64 = base64_encode(file_get_contents($image->getPathname()));

            // Obter usuário autenticado para análise personalizada
            $user = auth()->user();
            
            // Analisar com Gemini AI (com contexto do usuário se disponível)
            $analysis = $this->geminiService->analyzeFood($imageBase64, $user);

            // Incrementar contador de uso do usuário
            $user->incrementAnalysisUsage();

            // Log da análise para debug
            Log::info('AI Analysis Result', [
                'user_id' => $user->id,
                'food_name' => $analysis['food_name'],
                'calories' => $analysis['estimated_calories'],
                'confidence' => $analysis['confidence'],
                'remaining_today' => $user->getRemainingAnalysesToday(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Análise concluída com sucesso',
                'data' => array_merge($analysis, [
                    'usage_info' => [
                        'remaining_today' => $user->getRemainingAnalysesToday(),
                        'daily_limit' => $user->getCurrentPlan()->daily_analyses_limit,
                        'plan_name' => $user->getCurrentPlan()->display_name,
                    ]
                ])
            ]);

        } catch (Exception $e) {
            Log::error('AI Analysis Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao analisar a imagem: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Testa a conexão com a API do Gemini
     */
    public function testConnection(): JsonResponse
    {
        try {
            $isConnected = $this->geminiService->testConnection();

            return response()->json([
                'success' => $isConnected,
                'message' => $isConnected ? 'Conexão com Gemini AI funcionando' : 'Falha na conexão com Gemini AI',
                'data' => [
                    'connected' => $isConnected,
                    'service' => 'Google Gemini AI',
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao testar conexão: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Retorna informações sobre o serviço de IA
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Informações do serviço de IA',
            'data' => [
                'service' => 'Google Gemini AI',
                'model' => 'gemini-1.5-flash',
                'capabilities' => [
                    'image_analysis',
                    'food_recognition',
                    'calorie_estimation',
                    'ingredient_identification',
                    'nutritional_analysis'
                ],
                'supported_formats' => ['jpeg', 'png', 'jpg'],
                'max_file_size' => '10MB',
                'free_tier' => true,
                'rate_limits' => [
                    'requests_per_minute' => 15,
                    'requests_per_day' => 1500
                ]
            ]
        ]);
    }

    /**
     * Analisa múltiplas imagens (para planos premium)
     */
    public function analyzeBatch(Request $request): JsonResponse
    {
        try {
            // Validar a requisição
            $validator = Validator::make($request->all(), [
                'images' => 'required|array|max:5', // Máximo 5 imagens
                'images.*' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $results = [];
            $totalCalories = 0;

            foreach ($request->file('images') as $index => $image) {
                $imageBase64 = base64_encode(file_get_contents($image->getPathname()));
                $analysis = $this->geminiService->analyzeFood($imageBase64);
                
                $results[] = [
                    'index' => $index,
                    'analysis' => $analysis
                ];
                
                $totalCalories += $analysis['estimated_calories'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Análise em lote concluída',
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'total_images' => count($results),
                        'total_calories' => $totalCalories,
                        'average_calories' => $totalCalories / count($results),
                        'processed_at' => now()->toISOString()
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Batch AI Analysis Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao analisar as imagens: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
