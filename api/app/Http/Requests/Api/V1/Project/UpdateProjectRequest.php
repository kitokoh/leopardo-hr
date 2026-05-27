<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'string', 'max:150'], 'description' => ['nullable', 'string'], 'start_date' => ['nullable', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d'], 'members' => ['nullable', 'array'], 'members.*' => ['integer', 'min:1'], 'status' => ['nullable', 'in:active,completed,archived']];
    }
}
