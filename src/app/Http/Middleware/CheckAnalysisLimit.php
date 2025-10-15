<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAnalysisLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Verificar se o usuário pode fazer análise hoje
        if (!$user->canAnalyzeToday()) {
            $plan = $user->getCurrentPlan();
            $remaining = $user->getRemainingAnalysesToday();
            
            return response()->json([
                'success' => false,
                'message' => 'Limite de análises diárias atingido',
                'error_code' => 'ANALYSIS_LIMIT_REACHED',
                'data' => [
                    'current_plan' => $plan->display_name,
                    'daily_limit' => $plan->daily_analyses_limit,
                    'remaining_today' => $remaining,
                    'is_premium' => $user->hasPremiumPlan(),
                    'upgrade_message' => $user->hasPremiumPlan() 
                        ? 'Você atingiu o limite diário do seu plano.' 
                        : 'Faça upgrade para o plano Premium e tenha 10 análises por dia!',
                ]
            ], 429); // Too Many Requests
        }

        return $next($request);
    }
}
