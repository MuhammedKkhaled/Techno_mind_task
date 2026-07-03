<?php

namespace App\Http\Requests\Task;

use App\Http\Requests\BaseFormRequest;
use App\TaskStatusEnum;
use Illuminate\Validation\Rules\Enum;

class IndexTaskRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', new Enum(TaskStatusEnum::class)],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
