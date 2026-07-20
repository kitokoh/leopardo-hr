<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideSalaryAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['decision_comment' => ['nullable', 'string', 'max:1000'], 'repayment_months' => ['nullable', 'integer', 'min:1', 'max:24']];
    }
}
