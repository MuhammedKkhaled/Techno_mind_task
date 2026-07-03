<?php

namespace App\Http\Requests\Task;

use App\Http\Requests\BaseFormRequest;
use App\TaskStatusEnum;
use Illuminate\Validation\Rules\Enum;

class StoreTaskRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string' , 'max:1000'],
            'status' => ['required', new Enum(TaskStatusEnum::class)],
            'due_date' => ['nullable', 'date' , 'after_or_equal:today'],
        ];
    }
}
