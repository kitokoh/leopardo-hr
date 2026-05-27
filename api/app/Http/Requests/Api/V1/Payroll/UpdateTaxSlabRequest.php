<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxSlabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:150',
            'min_amount' => 'sometimes|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'fixed_deduction' => 'sometimes|numeric|min:0',
            'effective_from' => 'sometimes|date',
            'effective_to' => 'nullable|date',
        ];
    }
}
