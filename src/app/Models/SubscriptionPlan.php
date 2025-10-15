<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'price',
        'stripe_price_id',
        'daily_analyses_limit',
        'history_days_limit',
        'features',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Relacionamento com assinaturas de usuários
     */
    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Verificar se é plano gratuito
     */
    public function isFree(): bool
    {
        return $this->name === 'free' || $this->price == 0;
    }

    /**
     * Verificar se tem histórico ilimitado
     */
    public function hasUnlimitedHistory(): bool
    {
        return $this->history_days_limit === null;
    }

    /**
     * Obter features formatadas
     */
    public function getFormattedFeaturesAttribute(): array
    {
        $baseFeatures = [
            'analyses' => $this->daily_analyses_limit . ' análises por dia',
            'history' => $this->hasUnlimitedHistory() 
                ? 'Histórico ilimitado' 
                : "Histórico de {$this->history_days_limit} dias",
        ];

        return array_merge($baseFeatures, $this->features ?? []);
    }

    /**
     * Scope para planos ativos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para planos pagos
     */
    public function scopePaid($query)
    {
        return $query->where('price', '>', 0);
    }
}
