<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Compte rendu d'entretien (#9732).
 */
final class UpdateRecruitmentInterviewRequest extends FormRequest
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
            'status' => 'sometimes|in:scheduled,completed,cancelled,no_show',
            'feedback' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
        ];
    }
}
