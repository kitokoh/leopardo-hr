<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Correction d'une note (EDU-007, #5823 / EDU-010) — versionnée côté
 * service (journal edu_grade_versions).
 */
class CorrectEduGradeRequest extends FormRequest
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
            'score' => ['required', 'numeric'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
