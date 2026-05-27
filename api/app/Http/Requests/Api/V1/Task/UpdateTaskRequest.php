<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['title' => ['sometimes', 'string', 'max:200'], 'description' => ['nullable', 'string'], 'assigned_to' => ['sometimes', 'array'], 'assigned_to.*' => ['integer', 'min:1'], 'project_id' => ['nullable', 'integer', 'min:1'], 'due_date' => ['sometimes', 'date'], 'priority' => ['sometimes', 'in:low,normal,high,urgent'], 'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'], 'completed_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'], 'completion_note' => ['nullable', 'string', 'max:1000'], 'recurrence_rule' => ['nullable', 'string', 'max:120'], 'template_key' => ['nullable', 'string', 'max:100'], 'status' => ['sometimes', 'in:todo,inprogress,review,done,rejected,cancelled'], 'category' => ['nullable', 'string', 'max:100'], 'visibility' => ['sometimes', 'in:private,visible'], 'checklist' => ['nullable', 'array']];
    }
}
