<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\GlobalEmailUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->user()?->id;

        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'personal_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'recovery_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'personal_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:150',
                Rule::unique('employees', 'email')->ignore($employeeId),
                new GlobalEmailUnique((int) $employeeId),
            ],
        ];
    }
}
