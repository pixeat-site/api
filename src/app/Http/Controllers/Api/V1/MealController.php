<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Meal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MealController extends Controller
{
    /**
     * Listar refeições do usuário
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $query = Meal::where('user_id', $user->id);

            // Filtros opcionais
            if ($request->has('date')) {
                $date = Carbon::parse($request->date);
                $query->whereDate('consumed_at', $date);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $startDate = Carbon::parse($request->start_date);
                $endDate = Carbon::parse($request->end_date);
                $query->whereBetween('consumed_at', [$startDate, $endDate]);
            }

            if ($request->has('meal_type')) {
                $query->where('meal_type', $request->meal_type);
            }

            // Paginação
            $perPage = $request->get('per_page', 20);
            $meals = $query->orderBy('consumed_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $meals->items(),
                'pagination' => [
                    'current_page' => $meals->currentPage(),
                    'last_page' => $meals->lastPage(),
                    'per_page' => $meals->perPage(),
                    'total' => $meals->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar refeições: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar nova refeição
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'food_name' => 'required|string|max:255',
                'calories' => 'required|numeric|min:0',
                'meal_type' => 'required|in:breakfast,lunch,dinner,snack',
                'consumed_at' => 'nullable|date',
                'ingredients' => 'nullable|array',
                'description' => 'nullable|string|max:1000',
                'confidence' => 'nullable|numeric|between:0,1',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:10240', // Aceita upload de imagem (max 10MB)
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Processar upload de imagem (se fornecida)
            $imagePath = null;
            if ($request->hasFile('image')) {
                Log::info('📸 [MEAL] Upload de imagem detectado');
                $imagePath = $this->saveImageThumbnail($request->file('image'), $user->id);
                Log::info('✅ [MEAL] Imagem salva: ' . $imagePath);
            }

            $meal = Meal::create([
                'user_id' => $user->id,
                'food_name' => $request->food_name,
                'calories' => $request->calories,
                'meal_type' => $request->meal_type,
                'consumed_at' => $request->consumed_at ?? now()->format('Y-m-d H:i:s'),
                'ingredients' => $request->ingredients ? json_encode($request->ingredients) : null,
                'description' => $request->description,
                'confidence' => $request->confidence,
                'image_path' => $imagePath, // Salva URL pública da thumbnail
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refeição salva com sucesso',
                'data' => $meal
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ [MEAL] Erro ao salvar refeição: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar refeição: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salva thumbnail comprimida da imagem (baixa qualidade para storage)
     * Retorna a URL pública da imagem
     */
    private function saveImageThumbnail($imageFile, $userId): string
    {
        try {
            // Gerar nome único
            $filename = 'meal_' . $userId . '_' . time() . '_' . uniqid() . '.jpg';
            $path = 'meals/' . $filename;

            // Usar GD (PHP nativo) para comprimir imagem
            $sourcePath = $imageFile->getRealPath();
            $mimeType = mime_content_type($sourcePath);
            
            // Criar resource GD baseado no tipo de imagem
            switch ($mimeType) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                default:
                    throw new \Exception('Tipo de imagem não suportado: ' . $mimeType);
            }
            
            if ($sourceImage === false) {
                throw new \Exception('Não foi possível processar a imagem');
            }
            
            // Obter dimensões originais
            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);
            
            // Calcular novas dimensões (max 400px de largura, mantendo proporção)
            $maxWidth = 400;
            if ($originalWidth > $maxWidth) {
                $ratio = $maxWidth / $originalWidth;
                $newWidth = $maxWidth;
                $newHeight = intval($originalHeight * $ratio);
            } else {
                $newWidth = $originalWidth;
                $newHeight = $originalHeight;
            }
            
            // Criar nova imagem redimensionada
            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled(
                $thumbnail, 
                $sourceImage, 
                0, 0, 0, 0, 
                $newWidth, $newHeight, 
                $originalWidth, $originalHeight
            );
            
            // Salvar como JPEG com qualidade 60 (baixa para economizar espaço)
            $tempPath = sys_get_temp_dir() . '/' . $filename;
            imagejpeg($thumbnail, $tempPath, 60);
            
            // Liberar memória
            imagedestroy($sourceImage);
            imagedestroy($thumbnail);
            
            // Salvar no storage público
            Storage::disk('public')->put($path, file_get_contents($tempPath));
            unlink($tempPath); // Remover arquivo temporário
            
            // Retornar URL pública
            return Storage::disk('public')->url($path);
            
        } catch (\Exception $e) {
            Log::error('❌ [MEAL] Erro ao processar imagem: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mostrar refeição específica
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $meal = Meal::where('user_id', $user->id)->find($id);

            if (!$meal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refeição não encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $meal
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar refeição: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar refeição
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $meal = Meal::where('user_id', $user->id)->find($id);

            if (!$meal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refeição não encontrada'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'food_name' => 'sometimes|string|max:255',
                'calories' => 'sometimes|numeric|min:0',
                'meal_type' => 'sometimes|in:breakfast,lunch,dinner,snack',
                'consumed_at' => 'sometimes|date',
                'ingredients' => 'sometimes|array',
                'description' => 'sometimes|string|max:1000',
                'confidence' => 'sometimes|numeric|between:0,1',
                'image_path' => 'sometimes|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only([
                'food_name', 'calories', 'meal_type', 'consumed_at', 
                'description', 'confidence', 'image_path'
            ]);

            if ($request->has('ingredients')) {
                $updateData['ingredients'] = json_encode($request->ingredients);
            }

            $meal->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Refeição atualizada com sucesso',
                'data' => $meal->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar refeição: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar refeição
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $meal = Meal::where('user_id', $user->id)->find($id);

            if (!$meal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refeição não encontrada'
                ], 404);
            }

            $meal->delete();

            return response()->json([
                'success' => true,
                'message' => 'Refeição deletada com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao deletar refeição: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refeições de hoje
     */
    public function today(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $today = Carbon::today();
            $meals = Meal::where('user_id', $user->id)
                ->whereDate('consumed_at', $today)
                ->orderBy('consumed_at', 'asc')
                ->get();

            $totalCalories = $meals->sum('calories');

            return response()->json([
                'success' => true,
                'data' => [
                    'meals' => $meals,
                    'total_calories' => $totalCalories,
                    'date' => $today->format('Y-m-d')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar refeições de hoje: ' . $e->getMessage()
            ], 500);
        }
    }
}
