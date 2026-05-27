<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use Illuminate\Foundation\Http\FormRequest;

class ProjectIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['nullable', 'in:active,completed,archived'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
