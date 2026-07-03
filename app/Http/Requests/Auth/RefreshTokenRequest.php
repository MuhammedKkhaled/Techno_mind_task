<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class RefreshTokenRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }
}
