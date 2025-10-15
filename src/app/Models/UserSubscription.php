<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'status',
        'current_period_start',
        'current_period_end',
        'canceled_at',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    /**
     * Relacionamento com usuário
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relacionamento com plano
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Verificar se a assinatura está ativa
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               ($this->current_period_end === null || $this->current_period_end->isFuture());
    }

    /**
     * Verificar se a assinatura está cancelada
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled' || $this->canceled_at !== null;
    }

    /**
     * Verificar se está no período de graça
     */
    public function isInGracePeriod(): bool
    {
        return $this->isCanceled() && 
               $this->current_period_end !== null && 
               $this->current_period_end->isFuture();
    }

    /**
     * Obter dias restantes da assinatura
     */
    public function getDaysRemainingAttribute(): int
    {
        if (!$this->current_period_end) {
            return 0;
        }

        return max(0, $this->current_period_end->diffInDays(now()));
    }

    /**
     * Scope para assinaturas ativas
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('current_period_end')
                          ->orWhere('current_period_end', '>', now());
                    });
    }

    /**
     * Scope para assinaturas do Stripe
     */
    public function scopeStripe($query)
    {
        return $query->whereNotNull('stripe_subscription_id');
    }
}
