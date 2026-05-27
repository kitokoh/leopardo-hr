<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:200'],
            'sector' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
