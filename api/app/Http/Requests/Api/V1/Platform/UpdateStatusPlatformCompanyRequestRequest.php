<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusPlatformCompanyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:approved,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
        ];
    }
}
