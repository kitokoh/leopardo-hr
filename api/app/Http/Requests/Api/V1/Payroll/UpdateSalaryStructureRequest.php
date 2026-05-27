<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:150',
            'base_salary' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'country_code' => 'sometimes|string|size:2|in:DZ,MA,TN,FR,TR,SN',
            'frequency' => 'sometimes|in:monthly,bi_weekly,weekly',
            'active' => 'sometimes|boolean',
        ];
    }
}
