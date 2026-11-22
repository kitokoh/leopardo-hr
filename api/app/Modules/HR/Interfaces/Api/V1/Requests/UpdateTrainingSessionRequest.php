<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise a jour d'une session de formation (#9732).
 */
final class UpdateTrainingSessionRequest extends FormRequest
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
        /** @var Employee|null $user */
        $user = $this->user();
        $companyId = $user?->company_id;

        return [
            'trainer_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', $companyId),
            ],
            'external_trainer' => 'nullable|string|max:200',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'location' => 'nullable|string|max:200',
            'status' => 'sometimes|in:planned,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
        ];
    }
}
