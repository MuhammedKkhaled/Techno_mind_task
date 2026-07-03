<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseFormRequest extends FormRequest
{
    /**
     * Handle a failed validation attempt.
     */
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => $validator->errors()->first(),
            ], 422)
        );
    }
}
