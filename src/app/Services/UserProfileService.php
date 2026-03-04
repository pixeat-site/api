<?php

namespace App\Services;

/**
 * Serviço de perfil do usuário (Story 5.1).
 * Contém lógica de cálculo de calorias diárias (Harris-Benedict).
 */
class UserProfileService
{
    /**
     * Calcular calorias diárias usando fórmula de Harris-Benedict.
     */
    public function calculateDailyCalories(
        int $age,
        float $height,
        float $weight,
        int $activityLevel,
        int $goal,
        string $gender = 'male'
    ): float {
        $bmr = $gender === 'male'
            ? 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age)
            : 447.593 + (9.247 * $weight) + (3.098 * $height) - (4.330 * $age);

        $activityMultipliers = [
            0 => 1.2,   // Sedentário
            1 => 1.375, // Levemente ativo
            2 => 1.55,  // Moderadamente ativo
            3 => 1.725, // Muito ativo
            4 => 1.9,   // Extremamente ativo
        ];

        $tdee = $bmr * ($activityMultipliers[$activityLevel] ?? 1.375);

        return match ($goal) {
            0 => $tdee - 500, // Perder peso
            1 => $tdee,       // Manter peso
            2 => $tdee + 500, // Ganhar peso
            default => $tdee,
        };
    }
}
