<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Contract;

use Illuminate\Foundation\Http\FormRequest;

class RenewContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'base_salary' => 'nullable|numeric|min:0',
        ];
    }
}
