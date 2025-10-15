<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\Price;
use Stripe\Product;
use Stripe\Webhook;
use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Log;
use Exception;

class StripeService
{
    public function __construct()
    {
        // Configurar chave secreta do Stripe
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Criar ou obter cliente Stripe
     */
    public function getOrCreateCustomer(User $user): Customer
    {
        // Verificar se já tem customer_id
        if ($user->stripe_customer_id) {
            try {
                return Customer::retrieve($user->stripe_customer_id);
            } catch (Exception $e) {
                Log::warning('Stripe customer not found, creating new one', [
                    'user_id' => $user->id,
                    'old_customer_id' => $user->stripe_customer_id,
                ]);
            }
        }

        // Criar novo cliente
        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        // Salvar customer_id no usuário
        $user->update(['stripe_customer_id' => $customer->id]);

        Log::info('Stripe customer created', [
            'user_id' => $user->id,
            'customer_id' => $customer->id,
        ]);

        return $customer;
    }

    /**
     * Criar sessão de checkout
     */
    public function createCheckoutSession(User $user, SubscriptionPlan $plan): Session
    {
        if ($plan->isFree()) {
            throw new Exception('Não é possível criar checkout para plano gratuito');
        }

        $customer = $this->getOrCreateCustomer($user);

        // Criar produto e preço dinamicamente
        $product = Product::create([
            'name' => $plan->display_name,
            'description' => $plan->description,
            'metadata' => [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
            ],
        ]);

        $price = Price::create([
            'unit_amount' => (int) ($plan->price * 100), // Converter para centavos
            'currency' => 'brl',
            'recurring' => ['interval' => 'month'],
            'product' => $product->id,
            'metadata' => [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
            ],
        ]);

        Log::info('Stripe: Produto e preço criados dinamicamente', [
            'product_id' => $product->id,
            'price_id' => $price->id,
            'amount' => $plan->price,
            'plan_id' => $plan->id,
        ]);

        $session = Session::create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $price->id,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => 'pixeat://payment/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'pixeat://payment/cancelled',
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                ],
            ],
        ]);

        Log::info('Stripe checkout session created', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'session_id' => $session->id,
        ]);

        return $session;
    }

    /**
     * Processar webhook do Stripe
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $signature, $endpoint_secret);
        } catch (Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Invalid signature');
        }

        Log::info('Stripe webhook received', [
            'type' => $event['type'],
            'id' => $event['id'],
        ]);

        switch ($event['type']) {
            case 'checkout.session.completed':
                return $this->handleCheckoutCompleted($event['data']['object']);
                
            case 'customer.subscription.created':
                return $this->handleSubscriptionCreated($event['data']['object']);
                
            case 'customer.subscription.updated':
                return $this->handleSubscriptionUpdated($event['data']['object']);
                
            case 'customer.subscription.deleted':
                return $this->handleSubscriptionDeleted($event['data']['object']);
                
            case 'invoice.payment_succeeded':
                return $this->handlePaymentSucceeded($event['data']['object']);
                
            case 'invoice.payment_failed':
                return $this->handlePaymentFailed($event['data']['object']);
                
            default:
                Log::info('Unhandled webhook type', ['type' => $event['type']]);
                return ['status' => 'ignored'];
        }
    }

    /**
     * Processar checkout completado
     */
    private function handleCheckoutCompleted($session): array
    {
        $userId = $session['metadata']['user_id'] ?? null;
        $planId = $session['metadata']['plan_id'] ?? null;

        if (!$userId || !$planId) {
            Log::error('Missing metadata in checkout session', [
                'session_id' => $session['id'],
                'metadata' => $session['metadata'],
            ]);
            return ['status' => 'error', 'message' => 'Missing metadata'];
        }

        $user = User::find($userId);
        $plan = SubscriptionPlan::find($planId);

        if (!$user || !$plan) {
            Log::error('User or plan not found', [
                'user_id' => $userId,
                'plan_id' => $planId,
            ]);
            return ['status' => 'error', 'message' => 'User or plan not found'];
        }

        // Recuperar subscription do Stripe
        $stripeSubscription = Subscription::retrieve($session['subscription']);

        // Cancelar assinatura anterior se existir
        $currentSubscription = $user->currentSubscription;
        if ($currentSubscription && $currentSubscription->isActive()) {
            $currentSubscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);
        }

        // Criar nova assinatura
        UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_customer_id' => $stripeSubscription->customer,
            'status' => 'active',
            'current_period_start' => $stripeSubscription->current_period_start 
                ? now()->createFromTimestamp($stripeSubscription->current_period_start) 
                : now(),
            'current_period_end' => $stripeSubscription->current_period_end 
                ? now()->createFromTimestamp($stripeSubscription->current_period_end) 
                : now()->addMonth(),
        ]);

        Log::info('Subscription created from checkout', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => $stripeSubscription->id,
        ]);

        return ['status' => 'success'];
    }

    /**
     * Processar assinatura criada
     */
    private function handleSubscriptionCreated($subscription): array
    {
        Log::info('Subscription created webhook', [
            'subscription_id' => $subscription['id'],
            'customer' => $subscription['customer'],
        ]);

        return ['status' => 'success'];
    }

    /**
     * Processar assinatura atualizada
     */
    private function handleSubscriptionUpdated($subscription): array
    {
        $userSubscription = UserSubscription::where('stripe_subscription_id', $subscription['id'])->first();

        if (!$userSubscription) {
            Log::warning('Subscription not found for update', [
                'stripe_subscription_id' => $subscription['id'],
            ]);
            return ['status' => 'not_found'];
        }

        $userSubscription->update([
            'status' => $subscription['status'],
            'current_period_start' => now()->createFromTimestamp($subscription['current_period_start']),
            'current_period_end' => now()->createFromTimestamp($subscription['current_period_end']),
        ]);

        Log::info('Subscription updated', [
            'user_id' => $userSubscription->user_id,
            'status' => $subscription['status'],
        ]);

        return ['status' => 'success'];
    }

    /**
     * Processar assinatura cancelada
     */
    private function handleSubscriptionDeleted($subscription): array
    {
        $userSubscription = UserSubscription::where('stripe_subscription_id', $subscription['id'])->first();

        if (!$userSubscription) {
            Log::warning('Subscription not found for deletion', [
                'stripe_subscription_id' => $subscription['id'],
            ]);
            return ['status' => 'not_found'];
        }

        $userSubscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        Log::info('Subscription canceled', [
            'user_id' => $userSubscription->user_id,
        ]);

        return ['status' => 'success'];
    }

    /**
     * Processar pagamento bem-sucedido
     */
    private function handlePaymentSucceeded($invoice): array
    {
        Log::info('Payment succeeded', [
            'invoice_id' => $invoice['id'],
            'amount' => $invoice['amount_paid'],
            'customer' => $invoice['customer'],
        ]);

        return ['status' => 'success'];
    }

    /**
     * Processar falha no pagamento
     */
    private function handlePaymentFailed($invoice): array
    {
        Log::warning('Payment failed', [
            'invoice_id' => $invoice['id'],
            'amount' => $invoice['amount_due'],
            'customer' => $invoice['customer'],
        ]);

        // TODO: Notificar usuário sobre falha no pagamento
        // TODO: Implementar retry logic se necessário

        return ['status' => 'success'];
    }

    /**
     * Cancelar assinatura no Stripe
     */
    public function cancelSubscription(UserSubscription $userSubscription): bool
    {
        if (!$userSubscription->stripe_subscription_id) {
            return false;
        }

        try {
            $subscription = Subscription::retrieve($userSubscription->stripe_subscription_id);
            $subscription->cancel();

            Log::info('Stripe subscription canceled', [
                'user_id' => $userSubscription->user_id,
                'stripe_subscription_id' => $userSubscription->stripe_subscription_id,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to cancel Stripe subscription', [
                'user_id' => $userSubscription->user_id,
                'stripe_subscription_id' => $userSubscription->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Criar produto e preço no Stripe (para setup inicial)
     */
    public function createProductAndPrice(SubscriptionPlan $plan): array
    {
        try {
            // Criar produto
            $product = Product::create([
                'name' => $plan->display_name,
                'description' => $plan->description,
                'metadata' => [
                    'plan_id' => $plan->id,
                ],
            ]);

            // Criar preço
            $price = Price::create([
                'product' => $product->id,
                'unit_amount' => $plan->price * 100, // Converter para centavos
                'currency' => 'brl',
                'recurring' => [
                    'interval' => 'month',
                ],
                'metadata' => [
                    'plan_id' => $plan->id,
                ],
            ]);

            // Atualizar plano com o price_id
            $plan->update(['stripe_price_id' => $price->id]);

            Log::info('Stripe product and price created', [
                'plan_id' => $plan->id,
                'product_id' => $product->id,
                'price_id' => $price->id,
            ]);

            return [
                'product' => $product,
                'price' => $price,
            ];
        } catch (Exception $e) {
            Log::error('Failed to create Stripe product/price', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
