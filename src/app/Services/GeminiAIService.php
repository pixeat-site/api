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
    public function analyzeFood(string $imageBase64, $user = null): array
    {
        Log::info('🔍 [GEMINI] Iniciando análise de imagem');
        
        if (empty($this->apiKey)) {
            Log::warning('❌ [GEMINI] API key not configured, returning default analysis');
            return $this->getDefaultAnalysis();
        }

        Log::info('✅ [GEMINI] API key configurada');
        Log::info('📏 [GEMINI] Tamanho da imagem base64: ' . strlen($imageBase64) . ' caracteres');

        try {
            $prompt = $this->buildFoodAnalysisPrompt($user);
            Log::info('📝 [GEMINI] Prompt criado, enviando requisição...');
            if ($user) {
                Log::info('👤 [GEMINI] Análise personalizada para usuário: ' . $user->name . ' (' . $user->weight . 'kg, ' . $user->height . 'cm)');
            }
            
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
                        'temperature' => 0.2, // Aumentar um pouco para respostas mais variadas mas ainda precisas
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 2048, // Aumentar para análises mais detalhadas
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
    private function buildFoodAnalysisPrompt($user = null): string
    {
        // Informações do usuário para contextualizar a análise
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
- Idade: {$age} anos
- Peso: {$weight}kg
- Altura: {$height}cm  
- Nível de atividade: {$activityLevel}
- Objetivo: {$goal}
- Meta calórica diária: {$dailyCalories} kcal

IMPORTANTE: Use essas informações para avaliar se a porção está adequada para este perfil específico.
Para uma pessoa de {$weight}kg com objetivo de '{$goal}', ajuste suas estimativas de acordo.";
        }

        return "Você é um nutricionista brasileiro especializado em análise visual detalhada de alimentos. Analise esta imagem com EXTREMA PRECISÃO, identificando CADA ELEMENTO visível no prato.
{$userContext}

IMPORTANTE: Retorne APENAS o JSON sem qualquer texto adicional, markdown ou explicações.

{
  \"food_name\": \"Nome específico e completo do prato (ex: 'Prato executivo com arroz, feijão, bife e farofa')\",
  \"estimated_calories\": número_exato_de_calorias_baseado_no_volume_real,
  \"confidence\": valor_entre_0_e_1_da_sua_certeza,
  \"ingredients\": [\"ingrediente1\", \"ingrediente2\", \"ingrediente3\"],
  \"description\": \"Descrição detalhada listando TODOS os componentes visíveis\",
  \"portion_size\": \"pequena|média|grande\",
  \"nutritional_info\": {
    \"carbohydrates\": gramas_de_carboidratos,
    \"proteins\": gramas_de_proteínas,
    \"fats\": gramas_de_gorduras,
    \"fiber\": gramas_de_fibras
  }
}

METODOLOGIA DE ANÁLISE OBRIGATÓRIA:

🔍 PASSO 1 - VARREDURA COMPLETA DO PRATO:
- Examine CADA centímetro quadrado da imagem
- Identifique TODOS os alimentos, mesmo os pequenos
- Procure por: grãos, carnes, vegetais, molhos, temperos, farofa, queijo, etc.
- NÃO IGNORE elementos que parecem pequenos ou secundários

🔍 PASSO 2 - IDENTIFICAÇÃO ESPECÍFICA POR CATEGORIA:

CEREAIS/GRÃOS:
- Arroz (branco, integral, temperado): ~200kcal/xícara
- Macarrão/massa: ~220kcal/xícara
- Farofa (simples, bacon, ovos): ~150kcal/colher sopa
- Polenta: ~80kcal/fatia

LEGUMINOSAS:
- Feijão (carioca, preto, fradinho): ~150kcal/concha
- Lentilha: ~230kcal/xícara
- Grão-de-bico: ~270kcal/xícara

PROTEÍNAS:
- Bife/carne vermelha: ~250kcal/100g
- Frango grelhado: ~165kcal/100g
- Peixe: ~200kcal/100g
- Ovo frito: ~90kcal/unidade
- Linguiça: ~300kcal/100g

VEGETAIS/SALADAS:
- Alface, tomate, pepino: ~20kcal/xícara
- Batata frita: ~365kcal/100g
- Batata cozida: ~87kcal/100g
- Cenoura refogada: ~35kcal/100g

MOLHOS/TEMPEROS:
- Azeite/óleo: ~120kcal/colher sopa
- Molho de tomate: ~30kcal/colher sopa
- Maionese: ~100kcal/colher sopa

🔍 PASSO 3 - ESTIMATIVA DE VOLUME:
- Use referências visuais (prato, talheres, mãos)
- Prato raso padrão: ~23cm diâmetro
- Concha de feijão: ~80ml
- Colher de arroz: ~60g
- Porção de carne: compare com palma da mão

🔍 PASSO 4 - CÁLCULO FINAL:
- Some as calorias de CADA ingrediente identificado
- Ajuste pela porção real observada
- Considere método de preparo (frito +30%, grelhado normal)

EXEMPLOS DE ANÁLISE DETALHADA:

PRATO EXECUTIVO TÍPICO:
- Arroz branco (1 xícara): 200kcal
- Feijão carioca (1 concha): 150kcal  
- Bife grelhado (120g): 300kcal
- Farofa de bacon (2 col. sopa): 180kcal
- Salada (alface, tomate): 25kcal
- Batata frita (50g): 180kcal
TOTAL: ~1035kcal

LANCHE/SANDUÍCHE:
- Pão francês: 150kcal
- Presunto (30g): 45kcal
- Queijo (20g): 70kcal
- Maionese (1 col. chá): 35kcal
TOTAL: ~300kcal

REGRAS OBRIGATÓRIAS:
1. Liste NO MÍNIMO 4 ingredientes para pratos compostos
2. Seja ESPECÍFICO: \"farofa de bacon\" não \"farofa\"
3. Identifique o MÉTODO DE PREPARO: grelhado, frito, cozido
4. Considere TODOS os acompanhamentos visíveis
5. Estime porções baseado em referências visuais reais
6. Para pratos brasileiros, SEMPRE procure: arroz, feijão, proteína, farofa, salada

Retorne APENAS o JSON, sem ```json ou qualquer outro marcador.";
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
