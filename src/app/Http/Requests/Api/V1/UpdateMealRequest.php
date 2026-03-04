<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\BaseApiFormRequest;

class UpdateMealRequest extends BaseApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'food_name' => 'sometimes|string|max:255',
            'calories' => 'sometimes|numeric|min:0',
            'meal_type' => 'sometimes|in:breakfast,lunch,dinner,snack',
            'consumed_at' => 'sometimes|date',
            'ingredients' => 'sometimes|array',
            'description' => 'sometimes|string|max:1000',
            'confidence' => 'sometimes|numeric|between:0,1',
            'image_path' => 'sometimes|string|max:500',
        ];
    }
}
