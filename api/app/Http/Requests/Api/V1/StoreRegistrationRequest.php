<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'unique:employees,email',
                'max:150',
                new \App\Rules\GlobalEmailUnique,
            ],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
