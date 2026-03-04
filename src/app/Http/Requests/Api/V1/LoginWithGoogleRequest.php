<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\BaseApiFormRequest;

class LoginWithGoogleRequest extends BaseApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_token' => 'required|string',
        ];
    }
}
