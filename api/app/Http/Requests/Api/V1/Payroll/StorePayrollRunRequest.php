<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'country_code' => 'required|string|size:2|in:DZ,MA,TN,FR,TR,SN',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
