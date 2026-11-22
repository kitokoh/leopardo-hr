<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Suivi d'une inscription en formation (#9732).
 */
final class UpdateTrainingEnrollmentRequest extends FormRequest
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
            'status' => 'sometimes|in:enrolled,attended,completed,no_show,cancelled',
            'score' => 'nullable|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ];
    }
}
