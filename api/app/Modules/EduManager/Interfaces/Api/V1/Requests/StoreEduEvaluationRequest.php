<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une évaluation (EDU-010, #5826).
 */
class StoreEduEvaluationRequest extends FormRequest
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
            'class_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'type' => ['nullable', 'in:exam,quiz,homework,continuous'],
            'coefficient' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_score' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
        ];
    }
}
