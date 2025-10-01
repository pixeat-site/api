<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
