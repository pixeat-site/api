<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Serviço de análise de imagens de comida via Google Gemini.
 * Story 2.1: modelo configurável (estável + fallback), mime_type correto, prompt alinhado ao schema.
 * Schema da resposta: docs/architecture/analise-fluxo-analise-fotos.md
 */
class GeminiAIService
{
    private ?string $apiKey;
    private string $modelPrimary;
    private string $modelFallback;
    private string $version;
    private string $baseUrlTemplate = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->modelPrimary = config('services.gemini.model_primary', 'gemini-1.5-flash');
        $this->modelFallback = config('services.gemini.model_fallback', 'gemini-2.0-flash-exp');
        $this->version = config('services.gemini.version', 'v2');
    }

    /**
     * Analisa uma imagem de comida e retorna informações nutricionais.
     * Usa modelo principal; em falha, tenta fallback; depois análise padrão.
     *
     * @param  string  $imageBase64  Imagem em base64
     * @param  mixed  $user  Usuário autenticado (opcional) para contexto no prompt
     * @param  string|null  $mimeType  MIME da imagem (ex: image/jpeg, image/png). Se null, usa image/jpeg.
     */
    public function analyzeFood(string $imageBase64, $user = null, ?string $mimeType = null): array
    {
        Log::info('🔍 [GEMINI] Iniciando análise de imagem');

        if (empty($this->apiKey)) {
            Log::warning('❌ [GEMINI] API key not configured, returning default analysis');
            return $this->getDefaultAnalysis();
        }

        $mimeType = $this->normalizeMimeType($mimeType);
        Log::info('📏 [GEMINI] Tamanho base64: ' . strlen($imageBase64) . ' chars, mime: ' . $mimeType);

        $prompt = $this->buildFoodAnalysisPrompt($user);
        if ($user) {
            Log::info('👤 [GEMINI] Análise personalizada para usuário: ' . $user->name);
        }

        // Tentar modelo principal
        try {
            $start = microtime(true);
            $result = $this->callGemini($this->modelPrimary, $imageBase64, $mimeType, $prompt);
            if ($result !== null) {
                $latencyMs = (int) ((microtime(true) - $start) * 1000);
                Log::info('✅ [GEMINI] Análise completa (modelo primário)', [
                    'model_used' => $this->modelPrimary,
                    'latency_ms' => $latencyMs,
                ]);
                return $result;
            }
        } catch (Exception $e) {
            Log::warning('⚠️ [GEMINI] Falha no modelo principal', ['message' => $e->getMessage()]);
        }

        // Fallback para modelo alternativo
        if ($this->modelFallback !== $this->modelPrimary) {
            try {
                $start = microtime(true);
                $result = $this->callGemini($this->modelFallback, $imageBase64, $mimeType, $prompt);
                if ($result !== null) {
                    $latencyMs = (int) ((microtime(true) - $start) * 1000);
                    Log::info('✅ [GEMINI] Análise completa (modelo fallback)', [
                        'model_used' => $this->modelFallback,
                        'latency_ms' => $latencyMs,
                    ]);
                    return $result;
                }
            } catch (Exception $e) {
                Log::warning('⚠️ [GEMINI] Falha no modelo fallback', ['message' => $e->getMessage()]);
            }
        }

        Log::warning('⚠️ [GEMINI] Retornando análise padrão após falhas');
        return $this->getDefaultAnalysis();
    }

    /**
     * Chama a API do Gemini com o modelo indicado.
     *
     * @return array|null Análise normalizada ou null em caso de erro/parse falho
     */
    private function callGemini(string $model, string $imageBase64, string $mimeType, string $prompt): ?array
    {
        $url = sprintf($this->baseUrlTemplate, $model) . '?key=' . $this->apiKey;

        $response = Http::timeout(30)
            ->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $imageBase64,
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 2048,
                ]
            ]);

        if (! $response->successful()) {
            Log::error('❌ [GEMINI] API Error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new Exception('Erro na API do Gemini: ' . $response->body());
        }

        $data = $response->json();
        if (! isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            Log::error('❌ [GEMINI] Resposta inválida', ['data' => $data]);
            throw new Exception('Resposta inválida da API do Gemini');
        }

        $analysisText = $data['candidates'][0]['content']['parts'][0]['text'];
        return $this->parseGeminiResponse($analysisText);
    }

    /** Normaliza mime_type para valor aceito pelo Gemini (image/jpeg ou image/png). */
    private function normalizeMimeType(?string $mimeType): string
    {
        if (empty($mimeType)) {
            return 'image/jpeg';
        }
        $m = strtolower(trim($mimeType));
        if (str_contains($m, 'png')) {
            return 'image/png';
        }
        return 'image/jpeg';
    }

    /**
     * Constrói o prompt para análise de comida (schema alinhado ao contrato API/App).
     */
    private function buildFoodAnalysisPrompt($user = null): string
    {
        $userContext = '';
        if ($user) {
            $age = $user->age ?? 30;
            $weight = $user->weight ?? 70;
            $height = $user->height ?? 170;
            $activityLevels = ['Sedentário', 'Levemente ativo', 'Moderadamente ativo', 'Muito ativo', 'Extremamente ativo'];
            $activityLevel = $activityLevels[$user->activity_level] ?? 'Moderadamente ativo';
            $goals = ['Perder peso', 'Manter peso', 'Ganhar peso'];
            $goal = $goals[$user->goal] ?? 'Manter peso';
            $dailyCalories = $user->daily_calories ?? 2000;

            $userContext = "

CONTEXTO DO USUÁRIO:
- Idade: {$age} anos | Peso: {$weight}kg | Altura: {$height}cm
- Nível de atividade: {$activityLevel} | Objetivo: {$goal}
- Meta calórica diária: {$dailyCalories} kcal
Use essas informações para avaliar se a porção está adequada e ajuste as estimativas.";
        }

        return "Você é um nutricionista brasileiro. Analise esta imagem com precisão e identifique TODOS os elementos do prato.
{$userContext}

REGRAS OBRIGATÓRIAS:
1. Retorne APENAS um único objeto JSON válido, sem texto antes ou depois, sem markdown (sem ```json).
2. Use EXATAMENTE as chaves e tipos abaixo. portion_size deve ser uma das strings: \"pequena\", \"média\" ou \"grande\".

{
  \"food_name\": \"Nome completo e específico do prato (ex: Prato executivo com arroz, feijão, bife e farofa)\",
  \"estimated_calories\": número,
  \"confidence\": número entre 0 e 1,
  \"ingredients\": [\"ingrediente1\", \"ingrediente2\", \"ingrediente3\"],
  \"description\": \"Descrição detalhada listando todos os componentes visíveis\",
  \"portion_size\": \"pequena\" ou \"média\" ou \"grande\",
  \"nutritional_info\": {
    \"carbohydrates\": número em gramas,
    \"proteins\": número em gramas,
    \"fats\": número em gramas,
    \"fiber\": número em gramas
  }
}

METODOLOGIA:
- Varredura completa: grãos, carnes, vegetais, molhos, farofa, salada.
- Pratos brasileiros: sempre considerar arroz, feijão, proteína, acompanhamentos.
- Porção: use referências visuais (prato ~23cm, concha, colher). portion_size = pequena|média|grande conforme volume.
- Some calorias por ingrediente; ajuste por método de preparo (frito +30%, grelhado normal).
- Mínimo 4 ingredientes para pratos compostos; seja específico (ex: farofa de bacon).

Retorne somente o JSON.";
    }

    /**
     * Faz o parse da resposta do Gemini e normaliza ao schema (Story 2.1).
     */
    private function parseGeminiResponse(string $response): array
    {
        try {
            $cleanResponse = preg_replace('/```json\s*|\s*```/', '', $response);
            $cleanResponse = trim($cleanResponse);
            $data = json_decode($cleanResponse, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('JSON Parse Error', ['response' => substr($response, 0, 500)]);
                return $this->getDefaultAnalysis();
            }

            $portionSize = $data['portion_size'] ?? 'média';
            $validPortions = ['pequena', 'média', 'grande'];
            if (! in_array($portionSize, $validPortions, true)) {
                $portionSize = 'média';
            }

            $nut = is_array($data['nutritional_info'] ?? null) ? $data['nutritional_info'] : [];

            return [
                'food_name' => $data['food_name'] ?? 'Alimento não identificado',
                'estimated_calories' => (float) ($data['estimated_calories'] ?? 300),
                'confidence' => (float) ($data['confidence'] ?? 0.5),
                'ingredients' => $data['ingredients'] ?? ['Ingredientes não identificados'],
                'description' => $data['description'] ?? 'Não foi possível analisar a imagem',
                'portion_size' => $portionSize,
                'nutritional_info' => [
                    'carbohydrates' => (float) ($nut['carbohydrates'] ?? 30),
                    'proteins' => (float) ($nut['proteins'] ?? 15),
                    'fats' => (float) ($nut['fats'] ?? 10),
                    'fiber' => (float) ($nut['fiber'] ?? 5),
                ]
            ];
        } catch (Exception $e) {
            Log::error('Parse Gemini Response Error', ['error' => $e->getMessage()]);
            return $this->getDefaultAnalysis();
        }
    }

    private function getDefaultAnalysis(): array
    {
        return [
            'food_name' => 'Refeição',
            'estimated_calories' => 350.0,
            'confidence' => 0.3,
            'ingredients' => ['Não foi possível identificar os ingredientes'],
            'description' => 'Não foi possível analisar a imagem. Tente novamente com uma foto mais clara.',
            'portion_size' => 'média',
            'nutritional_info' => [
                'carbohydrates' => 35.0,
                'proteins' => 20.0,
                'fats' => 12.0,
                'fiber' => 5.0,
            ]
        ];
    }

    /** Retorna o nome do modelo principal em uso (para info()). */
    public function getModelPrimary(): string
    {
        return $this->modelPrimary;
    }

    /** Retorna o nome do modelo fallback (para info()). */
    public function getModelFallback(): string
    {
        return $this->modelFallback;
    }

    /** Retorna a versão lógica configurada da IA (Story 3.1). */
    public function getVersion(): string
    {
        return $this->version;
    }

    public function testConnection(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }
        try {
            $url = sprintf($this->baseUrlTemplate, $this->modelPrimary) . '?key=' . $this->apiKey;
            $response = Http::timeout(10)->post($url, [
                'contents' => [[ 'parts' => [['text' => 'Teste. Responda: OK']] ]]
            ]);
            return $response->successful();
        } catch (Exception $e) {
            Log::error('Gemini Connection Test Failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
