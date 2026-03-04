<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\BaseApiFormRequest;

class UpdateSettingsRequest extends BaseApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dark_mode' => 'sometimes|boolean',
            'notifications_enabled' => 'sometimes|boolean',
            'language' => 'sometimes|string|max:10',
        ];
    }
}
