<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class DailyUsage extends Model
{
    use HasFactory;

    protected $table = 'daily_usage';

    protected $fillable = [
        'user_id',
        'usage_date',
        'analyses_count',
    ];

    protected $casts = [
        'usage_date' => 'date',
    ];

    /**
     * Relacionamento com usuário
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Incrementar contador de análises
     */
    public function incrementAnalyses(): void
    {
        $this->increment('analyses_count');
    }

    /**
     * Verificar se pode fazer mais análises
     */
    public function canAnalyze(int $limit): bool
    {
        return $this->analyses_count < $limit;
    }

    /**
     * Obter ou criar registro de uso para hoje
     */
    public static function getTodayUsage(int $userId): self
    {
        return self::firstOrCreate([
            'user_id' => $userId,
            'usage_date' => Carbon::today(),
        ], [
            'analyses_count' => 0,
        ]);
    }

    /**
     * Scope para data específica
     */
    public function scopeForDate($query, Carbon $date)
    {
        return $query->where('usage_date', $date->toDateString());
    }

    /**
     * Scope para usuário específico
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para hoje
     */
    public function scopeToday($query)
    {
        return $query->where('usage_date', Carbon::today());
    }
}
