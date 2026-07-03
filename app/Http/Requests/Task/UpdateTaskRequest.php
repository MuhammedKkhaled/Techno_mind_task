<?php

namespace App\Http\Requests\Task;

use App\Http\Requests\BaseFormRequest;
use App\TaskStatusEnum;
use Illuminate\Validation\Rules\Enum;

class UpdateTaskRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string' , 'max:1000'],
            'status' => ['sometimes', 'nullable', new Enum(TaskStatusEnum::class)],
            'due_date' => ['sometimes', 'nullable', 'date' , 'after_or_equal:today'],
        ];
    }
}
