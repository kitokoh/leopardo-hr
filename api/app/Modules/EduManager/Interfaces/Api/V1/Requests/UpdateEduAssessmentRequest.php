<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Modules\EduManager\Domain\Models\EduAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une évaluation (EDU-010).
 */
class UpdateEduAssessmentRequest extends FormRequest
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
        return [
            'title' => ['sometimes', 'string', 'max:191'],
            'type' => ['sometimes', Rule::in(EduAssessment::TYPES)],
            'coefficient' => ['nullable', 'numeric', 'gt:0'],
            'max_score' => ['nullable', 'numeric', 'gt:0'],
            'assessment_date' => ['nullable', 'date'],
        ];
    }
}
