<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Meal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
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
                'image_path' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $meal = Meal::create([
                'user_id' => $user->id,
                'food_name' => $request->food_name,
                'calories' => $request->calories,
                'meal_type' => $request->meal_type,
                'consumed_at' => $request->consumed_at ?? now(),
                'ingredients' => $request->ingredients ? json_encode($request->ingredients) : null,
                'description' => $request->description,
                'confidence' => $request->confidence,
                'image_path' => $request->image_path,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refeição salva com sucesso',
                'data' => $meal
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar refeição: ' . $e->getMessage()
            ], 500);
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
