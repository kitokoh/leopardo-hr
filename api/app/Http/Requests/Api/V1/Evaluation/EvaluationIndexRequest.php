<?php

namespace App\Http\Requests\Api\V1\Evaluation;

use Illuminate\Foundation\Http\FormRequest;

class EvaluationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'employee_id' => [
                'nullable',
                'integer',
                'min:1',
                'exists:employees,id,company_id,'.$companyId,
            ],
            'evaluator_id' => [
                'nullable',
                'integer',
                'min:1',
                'exists:employees,id,company_id,'.$companyId,
            ],
            'period' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:draft,submitted,acknowledged'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => "Employ\u{00E9} introuvable dans votre entreprise.",
            'evaluator_id.exists' => "\u{00C9}valuateur introuvable dans votre entreprise.",
        ];
    }
}
