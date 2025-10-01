<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;

class AuthController extends Controller
{
    /**
     * Registro de usuário
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'age' => 'nullable|integer|min:1|max:120',
                'height' => 'nullable|numeric|min:50|max:250',
                'weight' => 'nullable|numeric|min:20|max:300',
                'target_weight' => 'nullable|numeric|min:20|max:300',
                'activity_level' => 'nullable|integer|between:0,4',
                'goal' => 'nullable|integer|between:0,2',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Calcular calorias diárias baseado nos dados
            $dailyCalories = $this->calculateDailyCalories(
                $request->age ?? 25,
                $request->height ?? 170,
                $request->weight ?? 70,
                $request->activity_level ?? 1,
                $request->goal ?? 0,
                'male' // Por padrão, pode ser adicionado no formulário
            );

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'age' => $request->age,
                'height' => $request->height,
                'weight' => $request->weight,
                'target_weight' => $request->target_weight,
                'activity_level' => $request->activity_level ?? 1,
                'goal' => $request->goal ?? 0,
                'daily_calories' => $dailyCalories,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Usuário registrado com sucesso',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login de usuário
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciais inválidas'
                ], 401);
            }

            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer login: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout de usuário
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout realizado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer logout: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Deletar token atual
            $request->user()->currentAccessToken()->delete();
            
            // Criar novo token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token renovado com sucesso',
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao renovar token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter perfil do usuário
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $request->user()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar perfil do usuário
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
                'age' => 'sometimes|integer|min:1|max:120',
                'height' => 'sometimes|numeric|min:50|max:250',
                'weight' => 'sometimes|numeric|min:20|max:300',
                'target_weight' => 'sometimes|numeric|min:20|max:300',
                'activity_level' => 'sometimes|integer|between:0,4',
                'goal' => 'sometimes|integer|between:0,2',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only([
                'name', 'email', 'age', 'height', 'weight', 
                'target_weight', 'activity_level', 'goal'
            ]);

            // Recalcular calorias se dados relevantes mudaram
            if ($request->hasAny(['age', 'height', 'weight', 'activity_level', 'goal'])) {
                $updateData['daily_calories'] = $this->calculateDailyCalories(
                    $request->age ?? $user->age,
                    $request->height ?? $user->height,
                    $request->weight ?? $user->weight,
                    $request->activity_level ?? $user->activity_level,
                    $request->goal ?? $user->goal,
                    'male' // Pode ser adicionado como campo
                );
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso',
                'data' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular calorias diárias usando fórmula de Harris-Benedict
     */
    private function calculateDailyCalories(int $age, float $height, float $weight, int $activityLevel, int $goal, string $gender = 'male'): float
    {
        // Calcular TMB (Taxa Metabólica Basal)
        if ($gender === 'male') {
            $bmr = 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age);
        } else {
            $bmr = 447.593 + (9.247 * $weight) + (3.098 * $height) - (4.330 * $age);
        }

        // Multiplicadores de atividade
        $activityMultipliers = [
            0 => 1.2,   // Sedentário
            1 => 1.375, // Levemente ativo
            2 => 1.55,  // Moderadamente ativo
            3 => 1.725, // Muito ativo
            4 => 1.9    // Extremamente ativo
        ];

        $tdee = $bmr * ($activityMultipliers[$activityLevel] ?? 1.375);

        // Ajustar baseado no objetivo
        switch ($goal) {
            case 0: // Perder peso
                return $tdee - 500; // Déficit de 500 calorias
            case 1: // Manter peso
                return $tdee;
            case 2: // Ganhar peso
                return $tdee + 500; // Superávit de 500 calorias
            default:
                return $tdee;
        }
    }
}
