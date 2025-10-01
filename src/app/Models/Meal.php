<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Meal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'food_name',
        'calories',
        'meal_type',
        'consumed_at',
        'ingredients',
        'description',
        'confidence',
        'image_path',
    ];

    protected $casts = [
        'calories' => 'float',
        'confidence' => 'float',
        'consumed_at' => 'datetime',
        'ingredients' => 'array',
    ];

    protected $hidden = [
        'user_id',
    ];

    protected $appends = [
        'ingredients_list',
        'formatted_consumed_at',
    ];

    /**
     * Relacionamento com usuário
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accessor para lista de ingredientes
     */
    public function getIngredientsListAttribute(): array
    {
        if (is_string($this->ingredients)) {
            return json_decode($this->ingredients, true) ?? [];
        }
        
        return $this->ingredients ?? [];
    }

    /**
     * Accessor para data formatada
     */
    public function getFormattedConsumedAtAttribute(): string
    {
        return $this->consumed_at ? $this->consumed_at->format('d/m/Y H:i') : '';
    }

    /**
     * Scope para refeições de hoje
     */
    public function scopeToday($query)
    {
        return $query->whereDate('consumed_at', Carbon::today());
    }

    /**
     * Scope para refeições por tipo
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('meal_type', $type);
    }

    /**
     * Scope para refeições por período
     */
    public function scopeBetweenDates($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('consumed_at', [$startDate, $endDate]);
    }

    /**
     * Scope para refeições do usuário
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Obter nome do tipo de refeição em português
     */
    public function getMealTypeNameAttribute(): string
    {
        $types = [
            'breakfast' => 'Café da Manhã',
            'lunch' => 'Almoço',
            'dinner' => 'Jantar',
            'snack' => 'Lanche',
        ];

        return $types[$this->meal_type] ?? $this->meal_type;
    }

    /**
     * Obter emoji do tipo de refeição
     */
    public function getMealTypeEmojiAttribute(): string
    {
        $emojis = [
            'breakfast' => '🌅',
            'lunch' => '🌞',
            'dinner' => '🌙',
            'snack' => '🍎',
        ];

        return $emojis[$this->meal_type] ?? '🍽️';
    }

    /**
     * Verificar se a refeição é de hoje
     */
    public function getIsTodayAttribute(): bool
    {
        return $this->consumed_at && $this->consumed_at->isToday();
    }

    /**
     * Obter nível de confiança em texto
     */
    public function getConfidenceLevelAttribute(): string
    {
        if (!$this->confidence) {
            return 'Não informado';
        }

        if ($this->confidence >= 0.8) {
            return 'Alta';
        } elseif ($this->confidence >= 0.6) {
            return 'Média';
        } else {
            return 'Baixa';
        }
    }

    /**
     * Formatar calorias
     */
    public function getFormattedCaloriesAttribute(): string
    {
        return number_format($this->calories, 0, ',', '.') . ' kcal';
    }
}
