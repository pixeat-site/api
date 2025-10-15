<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Plano Gratuito
        SubscriptionPlan::updateOrCreate(
            ['name' => 'free'],
            [
                'display_name' => 'Gratuito',
                'description' => 'Plano básico para começar a usar o PixEat',
                'price' => 0.00,
                'stripe_price_id' => null,
                'daily_analyses_limit' => 3,
                'history_days_limit' => 7,
                'features' => [
                    'Dashboard básico',
                    'Análise de IA básica',
                    'Suporte por email',
                ],
                'is_active' => true,
            ]
        );

        // Plano Premium
        SubscriptionPlan::updateOrCreate(
            ['name' => 'premium'],
            [
                'display_name' => 'Premium',
                'description' => 'Plano completo para usuários que querem o máximo do PixEat',
                'price' => 39.90,
                'stripe_price_id' => null, // Será preenchido após criar no Stripe
                'daily_analyses_limit' => 10,
                'history_days_limit' => null, // Ilimitado
                'features' => [
                    'Análise de IA avançada',
                    'Histórico ilimitado',
                    'Relatórios detalhados',
                    'Exportação de dados',
                    'Suporte prioritário',
                    'Dashboard avançado',
                ],
                'is_active' => true,
            ]
        );
    }
}
