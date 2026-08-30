<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Correction d'une note publiée (nouvelle version, motif obligatoire).
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
            'score' => ['required', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:500'],
            'correction_reason' => ['required', 'string', 'max:255'],
        ];
    }
}
