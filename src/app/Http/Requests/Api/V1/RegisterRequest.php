<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\BaseApiFormRequest;

class RegisterRequest extends BaseApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'age' => 'nullable|integer|min:1|max:120',
            'height' => 'nullable|numeric|min:50|max:250',
            'weight' => 'nullable|numeric|min:20|max:300',
            'target_weight' => 'nullable|numeric|min:20|max:300',
            'activity_level' => 'nullable|integer|between:0,4',
            'goal' => 'nullable|integer|between:0,2',
        ];
    }
}
