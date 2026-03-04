<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\BaseApiFormRequest;

class UpdateProfileRequest extends BaseApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $userId = $user ? $user->id : 0;

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $userId,
            'age' => 'sometimes|integer|min:1|max:120',
            'height' => 'sometimes|numeric|min:50|max:250',
            'weight' => 'sometimes|numeric|min:20|max:300',
            'target_weight' => 'sometimes|numeric|min:20|max:300',
            'activity_level' => 'sometimes|integer|between:0,4',
            'goal' => 'sometimes|integer|between:0,2',
        ];
    }
}
