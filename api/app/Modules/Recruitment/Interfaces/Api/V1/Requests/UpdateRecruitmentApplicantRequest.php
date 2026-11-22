<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Changement d'etape d'un candidat (#9732).
 */
final class UpdateRecruitmentApplicantRequest extends FormRequest
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
            'status' => 'sometimes|in:new,screening,interview,offer,hired,rejected,withdrawn',
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string',
            'status_reason' => 'nullable|string|max:500',
        ];
    }
}
