<?php

namespace App\Http\Requests\Api\V1\Evaluation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    public function rules(): array
    {
        return [
            'score' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'criteria' => ['nullable', 'array'],
            'criteria.*.label' => ['required_with:criteria', 'string', 'max:100'],
            'criteria.*.score' => ['required_with:criteria', 'numeric', 'min:0', 'max:5'],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'improvements' => ['nullable', 'string', 'max:2000'],
            'overall_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
