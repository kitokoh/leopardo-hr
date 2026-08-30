<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une évaluation (EDU-007, #5823 / EDU-010).
 */
class StoreEduAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'class_id' => [
                'required',
                'integer',
                Rule::exists('edu_classes', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('edu_subjects', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('edu_academic_years', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'title' => ['required', 'string', 'max:191'],
            'type' => ['required', Rule::in(EduAssessment::TYPES)],
            'coefficient' => ['nullable', 'numeric', 'gt:0'],
            'max_score' => ['nullable', 'numeric', 'gt:0'],
            'assessment_date' => ['nullable', 'date'],
        ];
    }
}
