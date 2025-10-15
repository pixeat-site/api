<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'stripe_customer_id',
        'age',
        'height',
        'weight',
        'target_weight',
        'activity_level',
        'goal',
        'daily_calories',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'age' => 'integer',
            'height' => 'float',
            'weight' => 'float',
            'target_weight' => 'float',
            'activity_level' => 'integer',
            'goal' => 'integer',
            'daily_calories' => 'float',
        ];
    }

    /**
     * Relacionamento com refeições
     */
    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    /**
     * Relacionamento com assinatura atual
     */
    public function currentSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)->active()->latest();
    }

    /**
     * Relacionamento com todas as assinaturas
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Relacionamento com uso diário
     */
    public function dailyUsages(): HasMany
    {
        return $this->hasMany(DailyUsage::class);
    }

    /**
     * Obter nome do nível de atividade
     */
    public function getActivityLevelNameAttribute(): string
    {
        $levels = [
            0 => 'Sedentário',
            1 => 'Levemente ativo',
            2 => 'Moderadamente ativo',
            3 => 'Muito ativo',
            4 => 'Extremamente ativo',
        ];

        return $levels[$this->activity_level] ?? 'Não informado';
    }

    /**
     * Obter nome do objetivo
     */
    public function getGoalNameAttribute(): string
    {
        $goals = [
            0 => 'Perder peso',
            1 => 'Manter peso',
            2 => 'Ganhar peso',
        ];

        return $goals[$this->goal] ?? 'Não informado';
    }

    /**
     * Obter plano atual do usuário
     */
    public function getCurrentPlan(): SubscriptionPlan
    {
        $subscription = $this->currentSubscription;
        
        if ($subscription && $subscription->isActive()) {
            return $subscription->subscriptionPlan;
        }
        
        // Retornar plano gratuito por padrão
        return SubscriptionPlan::where('name', 'free')->first() 
            ?? SubscriptionPlan::create([
                'name' => 'free',
                'display_name' => 'Gratuito',
                'price' => 0,
                'daily_analyses_limit' => 3,
                'history_days_limit' => 7,
            ]);
    }

    /**
     * Verificar se pode fazer análise hoje
     */
    public function canAnalyzeToday(): bool
    {
        $plan = $this->getCurrentPlan();
        $todayUsage = DailyUsage::getTodayUsage($this->id);
        
        return $todayUsage->canAnalyze($plan->daily_analyses_limit);
    }

    /**
     * Obter análises restantes hoje
     */
    public function getRemainingAnalysesToday(): int
    {
        $plan = $this->getCurrentPlan();
        $todayUsage = DailyUsage::getTodayUsage($this->id);
        
        return max(0, $plan->daily_analyses_limit - $todayUsage->analyses_count);
    }

    /**
     * Incrementar uso de análise
     */
    public function incrementAnalysisUsage(): void
    {
        $todayUsage = DailyUsage::getTodayUsage($this->id);
        $todayUsage->incrementAnalyses();
    }

    /**
     * Verificar se tem plano premium
     */
    public function hasPremiumPlan(): bool
    {
        $plan = $this->getCurrentPlan();
        return !$plan->isFree();
    }
}
