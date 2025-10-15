<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }
    /**
     * Listar planos disponíveis
     */
    public function plans(): JsonResponse
    {
        try {
            $plans = SubscriptionPlan::active()->get()->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'display_name' => $plan->display_name,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'daily_analyses_limit' => $plan->daily_analyses_limit,
                    'history_days_limit' => $plan->history_days_limit,
                    'features' => $plan->formatted_features,
                    'is_free' => $plan->isFree(),
                    'is_popular' => $plan->name === 'premium', // Marcar premium como popular
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar planos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter status da assinatura atual do usuário
     */
    public function status(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $currentPlan = $user->getCurrentPlan();
            $subscription = $user->currentSubscription;
            
            $data = [
                'current_plan' => [
                    'id' => $currentPlan->id,
                    'name' => $currentPlan->name,
                    'display_name' => $currentPlan->display_name,
                    'price' => $currentPlan->price,
                    'daily_analyses_limit' => $currentPlan->daily_analyses_limit,
                    'history_days_limit' => $currentPlan->history_days_limit,
                    'features' => $currentPlan->formatted_features,
                    'is_free' => $currentPlan->isFree(),
                ],
                'usage_today' => [
                    'used' => $currentPlan->daily_analyses_limit - $user->getRemainingAnalysesToday(),
                    'remaining' => $user->getRemainingAnalysesToday(),
                    'limit' => $currentPlan->daily_analyses_limit,
                    'percentage' => round((($currentPlan->daily_analyses_limit - $user->getRemainingAnalysesToday()) / $currentPlan->daily_analyses_limit) * 100, 1),
                ],
                'subscription' => null,
            ];

            if ($subscription && $subscription->isActive()) {
                $data['subscription'] = [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'current_period_end' => $subscription->current_period_end?->toISOString(),
                    'days_remaining' => $subscription->days_remaining,
                    'is_canceled' => $subscription->isCanceled(),
                    'is_in_grace_period' => $subscription->isInGracePeriod(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar status da assinatura: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar checkout session do Stripe
     */
    public function createCheckout(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'plan_id' => 'required|exists:subscription_plans,id',
            ]);

            /** @var User $user */
            $user = Auth::user();
            $plan = SubscriptionPlan::findOrFail($request->plan_id);
            
            if ($plan->isFree()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível criar checkout para plano gratuito'
                ], 400);
            }

            // Verificar se o plano tem price_id configurado
            if (!$plan->stripe_price_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plano não configurado no Stripe. Entre em contato com o suporte.',
                    'error_code' => 'PLAN_NOT_CONFIGURED'
                ], 400);
            }

            // Criar sessão de checkout
            $session = $this->stripeService->createCheckoutSession($user, $plan);

            return response()->json([
                'success' => true,
                'message' => 'Checkout criado com sucesso',
                'data' => [
                    'checkout_url' => $session->url,
                    'session_id' => $session->id,
                    'plan' => [
                        'name' => $plan->display_name,
                        'price' => $plan->price,
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Checkout creation failed', [
                'user_id' => Auth::id(),
                'plan_id' => $request->plan_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar checkout: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar assinatura
     */
    public function cancel(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $subscription = $user->currentSubscription;

            if (!$subscription || !$subscription->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhuma assinatura ativa encontrada'
                ], 404);
            }

            // Cancelar no Stripe
            $stripeCanceled = $this->stripeService->cancelSubscription($subscription);
            
            // Atualizar status local
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);

            Log::info('Subscription canceled', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Assinatura cancelada com sucesso',
                'data' => [
                    'canceled_at' => $subscription->canceled_at->toISOString(),
                    'valid_until' => $subscription->current_period_end?->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao cancelar assinatura: ' . $e->getMessage()
            ], 500);
        }
    }
}
