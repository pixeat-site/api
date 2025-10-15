<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;

class SetupStripeProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:setup-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Criar produtos e preços no Stripe para os planos existentes';

    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Configurando produtos no Stripe...');

        // Verificar se as chaves estão configuradas
        if (!config('services.stripe.secret')) {
            $this->error('❌ Chave secreta do Stripe não configurada!');
            $this->info('Configure STRIPE_SECRET no arquivo .env');
            return 1;
        }

        // Buscar planos pagos (não gratuitos)
        $plans = SubscriptionPlan::where('price', '>', 0)->get();

        if ($plans->isEmpty()) {
            $this->warn('⚠️  Nenhum plano pago encontrado para configurar no Stripe.');
            return 0;
        }

        foreach ($plans as $plan) {
            $this->info("📦 Configurando plano: {$plan->display_name}");

            try {
                // Verificar se já tem stripe_price_id
                if ($plan->stripe_price_id) {
                    $this->warn("   ⚠️  Plano já tem price_id: {$plan->stripe_price_id}");
                    
                    if (!$this->confirm('   Deseja recriar o produto no Stripe?')) {
                        continue;
                    }
                }

                // Criar produto e preço no Stripe
                $result = $this->stripeService->createProductAndPrice($plan);

                $this->info("   ✅ Produto criado:");
                $this->info("      Product ID: {$result['product']->id}");
                $this->info("      Price ID: {$result['price']->id}");
                $this->info("      Preço: R$ {$plan->price}/mês");

            } catch (\Exception $e) {
                $this->error("   ❌ Erro ao criar produto: {$e->getMessage()}");
                
                if ($this->option('verbose')) {
                    $this->error("      Trace: {$e->getTraceAsString()}");
                }
            }
        }

        $this->info('');
        $this->info('🎉 Configuração concluída!');
        $this->info('');
        $this->info('📋 Próximos passos:');
        $this->info('1. Configure o webhook no Stripe Dashboard');
        $this->info('2. Teste o checkout com cartão de teste');
        $this->info('3. Configure as variáveis de ambiente de produção');

        return 0;
    }
}
