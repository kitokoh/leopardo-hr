<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use Illuminate\Foundation\Http\FormRequest;

class TaskIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['project_id' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', 'in:todo,inprogress,review,done,rejected,cancelled'], 'priority' => ['nullable', 'in:low,normal,high,urgent'], 'assigned_to' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
