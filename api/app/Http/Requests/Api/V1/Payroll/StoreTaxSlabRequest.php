<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxSlabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country_code' => 'required|string|size:2|in:DZ,MA,TN,FR,TR,SN',
            'name' => 'required|string|max:150',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'rate' => 'required|numeric|min:0|max:100',
            'fixed_deduction' => 'nullable|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ];
    }
}
