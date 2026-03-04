<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\LoginWithGoogleRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Requests\Api\V1\UpdateSettingsRequest;
use App\Models\User;
use App\Services\GoogleAuthService;
use App\Services\UserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private UserProfileService $userProfileService,
        private GoogleAuthService $googleAuthService
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $dailyCalories = $this->userProfileService->calculateDailyCalories(
                (int) ($request->age ?? 25),
                (float) ($request->height ?? 170),
                (float) ($request->weight ?? 70),
                (int) ($request->activity_level ?? 1),
                (int) ($request->goal ?? 0),
                'male'
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
                    'token_type' => 'Bearer',
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar usuário: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciais inválidas',
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
                    'token_type' => 'Bearer',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer login: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout realizado com sucesso',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer logout: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $request->user()->currentAccessToken()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token renovado com sucesso',
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao renovar token: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function profile(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $request->user(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar perfil: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $updateData = $request->only([
                'name', 'email', 'age', 'height', 'weight',
                'target_weight', 'activity_level', 'goal',
            ]);

            if ($request->hasAny(['age', 'height', 'weight', 'activity_level', 'goal'])) {
                $updateData['daily_calories'] = $this->userProfileService->calculateDailyCalories(
                    (int) ($request->age ?? $user->age ?? 25),
                    (float) ($request->height ?? $user->height ?? 170),
                    (float) ($request->weight ?? $user->weight ?? 70),
                    (int) ($request->activity_level ?? $user->activity_level ?? 1),
                    (int) ($request->goal ?? $user->goal ?? 0),
                    'male'
                );
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso',
                'data' => $user->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar perfil: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function settings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = [
                'dark_mode' => (bool) ($user->dark_mode ?? false),
                'notifications_enabled' => (bool) ($user->notifications_enabled ?? true),
                'language' => (string) ($user->language ?? 'pt'),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar configurações: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $updateData = $request->only(['dark_mode', 'notifications_enabled', 'language']);
            $user->update(array_filter($updateData, fn ($v) => $v !== null));

            $user = $user->fresh();
            $data = [
                'dark_mode' => (bool) ($user->dark_mode ?? false),
                'notifications_enabled' => (bool) ($user->notifications_enabled ?? true),
                'language' => (string) ($user->language ?? 'pt'),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Configurações atualizadas com sucesso',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar configurações: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->tokens()->delete();
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Conta excluída com sucesso',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir conta: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function loginWithGoogle(LoginWithGoogleRequest $request): JsonResponse
    {
        try {
            $googleUser = $this->googleAuthService->verifyToken($request->id_token);

            if (!$googleUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token do Google inválido',
                ], 401);
            }

            $user = $this->googleAuthService->findOrCreateUser($googleUser);
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login com Google realizado com sucesso',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro no login com Google: ' . $e->getMessage(),
            ], 500);
        }
    }
}
