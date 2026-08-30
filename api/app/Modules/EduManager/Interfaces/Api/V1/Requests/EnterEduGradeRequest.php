<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Saisie d'une note (EDU-010, #5826).
 */
class EnterEduGradeRequest extends FormRequest
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
            'student_id' => ['required', 'integer'],
            'score' => ['required', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
