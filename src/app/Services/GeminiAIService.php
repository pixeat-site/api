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
        $this->modelPrimary = config('services.gemini.model_primary', 'gemini-2.0-flash');
        $this->modelFallback = config('services.gemini.model_fallback', 'gemini-2.5-flash');
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
                $result['analysis_source'] = 'gemini';
                $result['model_used'] = $this->modelPrimary;
                return $result;
            }
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && $e->getMessage() === 'GEMINI_QUOTA_EXCEEDED') {
                throw $e;
            }
            Log::warning('⚠️ [GEMINI] Falha no modelo principal', ['message' => $e->getMessage()]);
        }

        // Fallback para modelo alternativo (não tentar em caso de 429 — quota é por projeto)
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
                    $result['analysis_source'] = 'gemini';
                    $result['model_used'] = $this->modelFallback;
                    return $result;
                }
            } catch (\Throwable $e) {
                if ($e instanceof \RuntimeException && $e->getMessage() === 'GEMINI_QUOTA_EXCEEDED') {
                    throw $e;
                }
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

        // Imagem primeiro, depois o prompt (melhor para modelos de visão)
        $response = Http::timeout(60)
            ->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $imageBase64,
                                ]
                            ],
                            ['text' => $prompt],
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 2048,
                ],
                // Reduz bloqueio em fotos de comida (análise nutricional)
                'safetySettings' => [
                    ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                    ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
                    ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                    ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ],
            ]);

        if (! $response->successful()) {
            $status = $response->status();
            Log::error('❌ [GEMINI] API Error', ['status' => $status, 'body' => substr($response->body(), 0, 500)]);
            if ($status === 429) {
                throw new \RuntimeException('GEMINI_QUOTA_EXCEEDED', 429);
            }
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

        return "Analise a IMAGEM anexada (foto de prato/comida). Retorne APENAS o objeto JSON abaixo, sem texto antes ou depois e sem markdown.

Você é um nutricionista. A imagem é uma foto de refeição para um app de calorias. IDENTIFIQUE os alimentos visíveis na imagem e preencha o JSON com dados reais. Proibido usar \"Refeição\", \"Não foi possível identificar\" ou confidence 0,3 quando houver comida visível na foto; use confidence 0,7 ou mais e liste os ingredientes que você enxerga.
{$userContext}

REGRAS OBRIGATÓRIAS:
1. Retorne APENAS um único objeto JSON válido, sem texto antes ou depois, sem markdown (sem ```json).
2. Use EXATAMENTE as chaves e tipos abaixo. portion_size deve ser uma das strings: \"pequena\", \"média\" ou \"grande\".

{
  \"food_name\": \"Nome completo e específico do prato (ex: Prato executivo com arroz, feijão, bife e farofa)\",
  \"estimated_calories\": número,
  \"confidence\": número entre 0 e 1,
  \"ingredients\": [\"ingrediente1\", \"ingrediente2\", \"ingrediente3\", ...],
  \"description\": \"Descrição detalhada listando todos os componentes visíveis e porções (ex: 1 concha de arroz, 1 bife médio, salada com folhas e tomate)\",
  \"portion_size\": \"pequena\" ou \"média\" ou \"grande\",
  \"nutritional_info\": {
    \"carbohydrates\": número em gramas,
    \"proteins\": número em gramas,
    \"fats\": número em gramas,
    \"fiber\": número em gramas
  }
}

METODOLOGIA OBRIGATÓRIA (calorias por ingrediente → soma):
1. Liste CADA alimento/componente visível em \"ingredients\" (mínimo 3–5 itens para pratos compostos). Seja específico: \"arroz branco\", \"feijão preto\", \"bife grelhado\", \"farofa de bacon\", \"salada de alface e tomate\".
2. Para CADA ingrediente, estime as calorias mentalmente (ex: arroz 1 concha ≈ 150 kcal, feijão 1 concha ≈ 130 kcal, bife médio ≈ 180 kcal). Some tudo e coloque o total em \"estimated_calories\".
3. Ajuste por método de preparo: frito +20–30%, grelhado/cozido normal. Porção: prato ~23 cm, concha cheia, etc.
4. nutritional_info: distribua o total de calorias em carboidratos, proteínas, gorduras e fibras de forma coerente com o tipo de prato (ex: arroz/feijão mais carbs, carne mais proteína).
5. description: descreva o que está no prato e as quantidades visíveis (ex: \"Arroz branco (1 concha), feijão preto (1 concha), bife grelhado médio, farofa, salada verde com tomate\").

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
                $cleanResponse = $this->extractJsonFromText($cleanResponse);
                $data = $cleanResponse !== null ? json_decode($cleanResponse, true) : null;
            }
            if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
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

    /** Extrai o primeiro objeto JSON do texto (ex.: quando o modelo adiciona frase antes/depois). */
    private function extractJsonFromText(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }
        $depth = 0;
        $len = strlen($text);
        for ($i = $start; $i < $len; $i++) {
            $c = $text[$i];
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }
        return null;
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
            ,
            // Observabilidade: permite diferenciar "IA rodou" vs fallback padrão.
            // Campo extra e opcional: não quebra o contrato existente do app.
            'analysis_source' => 'default',
            'model_used' => null,
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
