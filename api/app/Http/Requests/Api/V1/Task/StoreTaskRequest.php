<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string'], 'assigned_to' => ['nullable', 'array'], 'assigned_to.*' => ['integer', 'min:1'], 'project_id' => ['nullable', 'integer', 'min:1'], 'due_date' => ['required', 'date'], 'priority' => ['nullable', 'in:low,normal,high,urgent'], 'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'], 'recurrence_rule' => ['nullable', 'string', 'max:120'], 'template_key' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:100'], 'visibility' => ['nullable', 'in:private,visible'], 'checklist' => ['nullable', 'array']];
    }
}
