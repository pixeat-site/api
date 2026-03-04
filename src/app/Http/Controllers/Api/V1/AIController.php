<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AnalysisImageService;
use App\Services\GeminiAIService;
use App\Services\GroqAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class AIController extends Controller
{
    private GeminiAIService $geminiService;

    private GroqAIService $groqService;

    private AnalysisImageService $analysisImageService;

    public function __construct(GeminiAIService $geminiService, GroqAIService $groqService, AnalysisImageService $analysisImageService)
    {
        $this->geminiService = $geminiService;
        $this->groqService = $groqService;
        $this->analysisImageService = $analysisImageService;
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

            // Normalizar imagem (redimensionar/comprimir) para caber no Groq e reduzir payload
            $image = $request->file('image');
            $normalized = $this->analysisImageService->normalize($image);
            $imageBase64 = $normalized['base64'];
            $mimeType = $normalized['mime_type'];
            $user = auth()->user();

            $analysis = $this->geminiService->analyzeFood($imageBase64, $user, $mimeType);

            // Se a IA não rodou (fallback padrão), não consumir cota e responder como indisponível.
            if (($analysis['analysis_source'] ?? null) === 'default') {
                Log::warning('AI Analysis Unavailable (default fallback)', ['user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Serviço de IA temporariamente indisponível. Tente novamente em alguns minutos.',
                    'data' => null,
                ], 503);
            }

            // Resposta genérica (Refeição, 0.3) = modelo não analisou de fato; não contar como sucesso.
            $isGeneric = ($analysis['food_name'] ?? '') === 'Refeição'
                && (float) ($analysis['confidence'] ?? 0) <= 0.4;
            if ($isGeneric) {
                Log::warning('AI Analysis Generic (low confidence)', ['user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível analisar esta imagem. Tente outra foto ou aguarde alguns minutos (limite de uso da IA).',
                    'data' => null,
                ], 503);
            }

            // Incrementar contador de uso do usuário (somente quando houve análise real)
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

        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'GEMINI_QUOTA_EXCEEDED') {
                $user = auth()->user();
                if (isset($imageBase64, $mimeType) && $this->groqService->isConfigured()) {
                    $analysis = $this->groqService->analyzeFood($imageBase64, $user, $mimeType);
                    if ($analysis !== null) {
                        $isGeneric = ($analysis['food_name'] ?? '') === 'Refeição'
                            && (float) ($analysis['confidence'] ?? 0) <= 0.4;
                        if (! $isGeneric) {
                            $user->incrementAnalysisUsage();
                            Log::info('AI Analysis Result (Groq fallback)', [
                                'user_id' => $user->id,
                                'food_name' => $analysis['food_name'],
                            ]);
                            return response()->json([
                                'success' => true,
                                'message' => 'Análise concluída com sucesso',
                                'data' => array_merge($analysis, [
                                    'usage_info' => [
                                        'remaining_today' => $user->getRemainingAnalysesToday(),
                                        'daily_limit' => $user->getCurrentPlan()->daily_analyses_limit,
                                        'plan_name' => $user->getCurrentPlan()->display_name,
                                    ],
                                ]),
                            ], 200);
                        }
                    }
                }
                Log::warning('AI Analysis Quota Exceeded (429), Groq indisponível ou sem resultado', ['user_id' => auth()->id()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de uso da IA atingido. Tente novamente em alguns minutos.',
                    'data' => null,
                ], 503);
            }
            throw $e;
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
     * Informações do serviço de IA (Stories 2.1 e 3.1: modelo em uso, fallback e versão lógica).
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Informações do serviço de IA',
            'data' => [
                'service' => 'Google Gemini AI',
                'version' => $this->geminiService->getVersion(),
                'model' => $this->geminiService->getModelPrimary(),
                'model_fallback' => $this->geminiService->getModelFallback(),
                'capabilities' => [
                    'image_analysis',
                    'food_recognition',
                    'calorie_estimation',
                    'ingredient_identification',
                    'nutritional_analysis',
                ],
                'supported_formats' => ['jpeg', 'png', 'jpg'],
                'max_file_size' => '10MB',
                'response_schema' => 'food_name, estimated_calories, confidence, ingredients, description, portion_size, nutritional_info',
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

            $user = auth()->user();
            foreach ($request->file('images') as $index => $image) {
                $imageBase64 = base64_encode(file_get_contents($image->getPathname()));
                $mimeType = $image->getMimeType();
                $analysis = $this->geminiService->analyzeFood($imageBase64, $user, $mimeType);
                
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
