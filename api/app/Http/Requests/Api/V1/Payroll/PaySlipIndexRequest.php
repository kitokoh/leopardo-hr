<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PaySlipIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payroll_run_id' => 'sometimes|nullable|integer',
            'status' => 'sometimes|nullable|string|in:calculated,validated,sent',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'sort_by' => 'sometimes|nullable|string|in:period_start,period_end,net_salary,status,id',
            'sort_dir' => 'sometimes|nullable|string|in:asc,desc',
        ];
    }
}
