<?php

namespace App\Http\Requests\Api\V1\Cameras;

use Illuminate\Foundation\Http\FormRequest;

class StoreCameraPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'min:1'],
            'can_view' => ['sometimes', 'boolean'],
            'can_share' => ['sometimes', 'boolean'],
            'can_manage' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date_format:Y-m-d\TH:i:sP', 'after:now'],
        ];
    }
}
