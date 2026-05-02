<?php

namespace App\Http\Requests\Api\V1\Evaluation;

use App\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Employee|null $actor */
        $actor = $this->user();
        $companyId = $actor?->company_id;

        return [
            'employee_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $companyId)
                ),
            ],
            'period' => ['required', 'string', 'max:20'],
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
