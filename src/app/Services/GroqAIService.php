<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Serviço de análise de imagens de comida via Groq (Llama 4 Scout).
 * Usado como fallback quando o Gemini retorna 429 (quota excedida).
 * API compatível com OpenAI: chat/completions com visão.
 * Limite: imagem base64 até 4MB (Groq); imagens maiores são ignoradas.
 */
class GroqAIService
{
    /** Groq limita imagem base64 a 4MB no request; usamos ~3MB de base64 para margem. */
    private const GROQ_BASE64_MAX_CHARS = 3 * 1024 * 1024;

    private ?string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
        $this->model = config('services.groq.model', 'meta-llama/llama-4-scout-17b-16e-instruct');
    }

    /**
     * Analisa uma imagem de comida e retorna o mesmo schema do Gemini.
     * Retorna null se API key ausente, imagem grande demais ou erro.
     */
    public function analyzeFood(string $imageBase64, $user = null, ?string $mimeType = null): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('❌ [GROQ] API key not configured');
            return null;
        }

        $mimeType = $this->normalizeMimeType($mimeType);
        $base64Len = strlen($imageBase64);
        if ($base64Len > self::GROQ_BASE64_MAX_CHARS) {
            Log::warning('⚠️ [GROQ] Imagem muito grande para Groq (base64 > ~3MB), pulando', ['chars' => $base64Len]);
            return null;
        }

        Log::info('🔍 [GROQ] Iniciando análise de imagem (fallback)', ['model' => $this->model]);
        $prompt = $this->buildFoodAnalysisPrompt($user);

        $imageUrl = 'data:' . $mimeType . ';base64,' . $imageBase64;
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => $imageUrl],
                        ],
                    ],
                ],
            ],
            'temperature' => 0.3,
            'max_tokens' => 2048,
            'response_format' => ['type' => 'json_object'],
        ];

        try {
            $response = Http::timeout(60)
                ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
                ->post($this->baseUrl, $payload);

            if (! $response->successful()) {
                $status = $response->status();
                Log::warning('❌ [GROQ] API Error', ['status' => $status, 'body' => substr($response->body(), 0, 400)]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            if (empty($content)) {
                Log::warning('❌ [GROQ] Resposta sem content');
                return null;
            }

            $result = $this->parseAnalysisResponse($content);
            if ($result !== null) {
                $result['analysis_source'] = 'groq';
                $result['model_used'] = $this->model;
                Log::info('✅ [GROQ] Análise completa');
            }
            return $result;
        } catch (Exception $e) {
            Log::warning('⚠️ [GROQ] Falha', ['message' => $e->getMessage()]);
            return null;
        }
    }

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
            $userContext = "\n\nCONTEXTO DO USUÁRIO:\n- Idade: {$age} anos | Peso: {$weight}kg | Altura: {$height}cm\n- Nível de atividade: {$activityLevel} | Objetivo: {$goal}\n- Meta calórica diária: {$dailyCalories} kcal\nUse essas informações para avaliar se a porção está adequada e ajuste as estimativas.";
        }

        return "Analise a IMAGEM anexada (foto de prato/comida). Retorne APENAS o objeto JSON abaixo, sem texto antes ou depois e sem markdown.

Você é um nutricionista. A imagem é uma foto de refeição para um app de calorias. IDENTIFIQUE os alimentos visíveis na imagem e preencha o JSON com dados reais. Proibido usar \"Refeição\", \"Não foi possível identificar\" ou confidence 0,3 quando houver comida visível na foto; use confidence 0,7 ou mais e liste os ingredientes que você enxerga.
{$userContext}

REGRAS OBRIGATÓRIAS:
1. Retorne APENAS um único objeto JSON válido, sem texto antes ou depois, sem markdown (sem ```json).
2. Use EXATAMENTE as chaves e tipos abaixo. portion_size deve ser uma das strings: \"pequena\", \"média\" ou \"grande\".

{
  \"food_name\": \"Nome completo e específico do prato\",
  \"estimated_calories\": número,
  \"confidence\": número entre 0 e 1,
  \"ingredients\": [\"ingrediente1\", \"ingrediente2\", ...],
  \"description\": \"Descrição detalhada listando todos os componentes visíveis\",
  \"portion_size\": \"pequena\" ou \"média\" ou \"grande\",
  \"nutritional_info\": {
    \"carbohydrates\": número em gramas,
    \"proteins\": número em gramas,
    \"fats\": número em gramas,
    \"fiber\": número em gramas
  }
}

Retorne somente o JSON.";
    }

    private function parseAnalysisResponse(string $response): ?array
    {
        $clean = preg_replace('/```json\s*|\s*```/', '', $response);
        $clean = trim($clean);
        $data = json_decode($clean, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $extracted = $this->extractJsonFromText($clean);
            $data = $extracted !== null ? json_decode($extracted, true) : null;
        }
        if ($data === null || ! is_array($data)) {
            return null;
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
            ],
        ];
    }

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

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }
}
