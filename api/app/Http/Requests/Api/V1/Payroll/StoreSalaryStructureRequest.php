<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'base_salary' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'country_code' => 'required|string|size:2|in:DZ,MA,TN,FR,TR,SN',
            'frequency' => 'nullable|in:monthly,bi_weekly,weekly',
        ];
    }
}
