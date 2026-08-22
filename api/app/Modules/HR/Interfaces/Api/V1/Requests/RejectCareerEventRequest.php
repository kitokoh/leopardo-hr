<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rejet d'un événement de carrière (issue #5259) — motif optionnel.
 */
class RejectCareerEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Employee|null $user */
        $user = $this->user();

        return $user?->isManager() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
