<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\AI;

use Illuminate\Foundation\Http\FormRequest;

class PreparePayrollAIWorkflowRequest extends FormRequest
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
        ];
    }
}
