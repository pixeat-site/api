<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\BaseApiFormRequest;

class StoreMealRequest extends BaseApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'food_name' => 'required|string|max:255',
            'calories' => 'required|numeric|min:0',
            'meal_type' => 'required|in:breakfast,lunch,dinner,snack',
            'consumed_at' => 'nullable|date',
            'ingredients' => 'nullable|array',
            'description' => 'nullable|string|max:1000',
            'confidence' => 'nullable|numeric|between:0,1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
        ];
    }
}
