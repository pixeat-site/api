<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiAIService
{
    private ?string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        // Usando modelo Gemini 2.0 Flash Experimental (mais recente e funcional)
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent';
    }

    /**
     * Analisa uma imagem de comida e retorna informações nutricionais
     */
    public function analyzeFood(string $imageBase64): array
    {
        Log::info('🔍 [GEMINI] Iniciando análise de imagem');
        
        if (empty($this->apiKey)) {
            Log::warning('❌ [GEMINI] API key not configured, returning default analysis');
            return $this->getDefaultAnalysis();
        }

        Log::info('✅ [GEMINI] API key configurada');
        Log::info('📏 [GEMINI] Tamanho da imagem base64: ' . strlen($imageBase64) . ' caracteres');

        try {
            $prompt = $this->buildFoodAnalysisPrompt();
            Log::info('📝 [GEMINI] Prompt criado, enviando requisição...');
            
            $response = Http::timeout(30)
                ->post($this->baseUrl . '?key=' . $this->apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt
                                ],
                                [
                                    'inline_data' => [
                                        'mime_type' => 'image/jpeg',
                                        'data' => $imageBase64
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'topK' => 32,
                        'topP' => 1,
                        'maxOutputTokens' => 1024,
                    ]
                ]);

            if (!$response->successful()) {
                Log::error('❌ [GEMINI] API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception('Erro na API do Gemini: ' . $response->body());
            }

            Log::info('📥 [GEMINI] Resposta recebida com sucesso');
            $data = $response->json();
            Log::info('📊 [GEMINI] Resposta JSON parseada');
            
            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                Log::error('❌ [GEMINI] Resposta inválida - estrutura inesperada', ['data' => $data]);
                throw new Exception('Resposta inválida da API do Gemini');
            }

            $analysisText = $data['candidates'][0]['content']['parts'][0]['text'];
            Log::info('📝 [GEMINI] Texto da análise extraído: ' . substr($analysisText, 0, 200) . '...');
            
            $result = $this->parseGeminiResponse($analysisText);
            Log::info('✅ [GEMINI] Análise completa', $result);
            
            return $result;

        } catch (Exception $e) {
            Log::error('❌❌❌ [GEMINI] AI Analysis Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Log::warning('⚠️ [GEMINI] Retornando análise padrão devido ao erro');
            // Retornar dados padrão em caso de erro
            return $this->getDefaultAnalysis();
        }
    }

    /**
     * Constrói o prompt para análise de comida
     */
    private function buildFoodAnalysisPrompt(): string
    {
        return "Analise esta imagem de comida e forneça as seguintes informações em formato JSON válido:

{
  \"food_name\": \"Nome do prato ou alimento principal\",
  \"estimated_calories\": número_de_calorias_estimado,
  \"confidence\": valor_entre_0_e_1_indicando_confiança,
  \"ingredients\": [\"lista\", \"de\", \"ingredientes\", \"identificados\"],
  \"description\": \"Descrição detalhada do que você vê na imagem\",
  \"portion_size\": \"Tamanho da porção estimado (pequena/média/grande)\",
  \"nutritional_info\": {
    \"carbohydrates\": gramas_estimadas,
    \"proteins\": gramas_estimadas,
    \"fats\": gramas_estimadas,
    \"fiber\": gramas_estimadas
  }
}

Instruções importantes:
1. Seja preciso na estimativa de calorias baseado no que você vê
2. Se não conseguir identificar claramente, use confidence baixo (0.3-0.5)
3. Para pratos brasileiros, considere ingredientes típicos
4. Estime o tamanho da porção baseado em referências visuais
5. Retorne APENAS o JSON, sem texto adicional
6. Se não conseguir analisar, retorne confidence 0.1 e estimativa conservadora";
    }

    /**
     * Faz o parse da resposta do Gemini
     */
    private function parseGeminiResponse(string $response): array
    {
        try {
            // Limpar a resposta removendo markdown se houver
            $cleanResponse = preg_replace('/```json\s*|\s*```/', '', $response);
            $cleanResponse = trim($cleanResponse);
            
            $data = json_decode($cleanResponse, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('JSON Parse Error', ['response' => $response]);
                return $this->getDefaultAnalysis();
            }

            // Validar e normalizar os dados
            return [
                'food_name' => $data['food_name'] ?? 'Alimento não identificado',
                'estimated_calories' => (float) ($data['estimated_calories'] ?? 300),
                'confidence' => (float) ($data['confidence'] ?? 0.5),
                'ingredients' => $data['ingredients'] ?? ['Ingredientes não identificados'],
                'description' => $data['description'] ?? 'Não foi possível analisar a imagem',
                'portion_size' => $data['portion_size'] ?? 'média',
                'nutritional_info' => [
                    'carbohydrates' => (float) ($data['nutritional_info']['carbohydrates'] ?? 30),
                    'proteins' => (float) ($data['nutritional_info']['proteins'] ?? 15),
                    'fats' => (float) ($data['nutritional_info']['fats'] ?? 10),
                    'fiber' => (float) ($data['nutritional_info']['fiber'] ?? 5),
                ]
            ];

        } catch (Exception $e) {
            Log::error('Parse Gemini Response Error', ['error' => $e->getMessage()]);
            return $this->getDefaultAnalysis();
        }
    }

    /**
     * Retorna análise padrão em caso de erro
     */
    private function getDefaultAnalysis(): array
    {
        return [
            'food_name' => 'Refeição',
            'estimated_calories' => 350.0,
            'confidence' => 0.3,
            'ingredients' => ['Não foi possível identificar os ingredientes'],
            'description' => 'Não foi possível analisar a imagem. Por favor, tente novamente com uma foto mais clara.',
            'portion_size' => 'média',
            'nutritional_info' => [
                'carbohydrates' => 35.0,
                'proteins' => 20.0,
                'fats' => 12.0,
                'fiber' => 5.0,
            ]
        ];
    }

    /**
     * Testa a conexão com a API
     */
    public function testConnection(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->post($this->baseUrl . '?key=' . $this->apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => 'Teste de conexão. Responda apenas: "OK"'
                                ]
                            ]
                        ]
                    ]
                ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Gemini Connection Test Failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
