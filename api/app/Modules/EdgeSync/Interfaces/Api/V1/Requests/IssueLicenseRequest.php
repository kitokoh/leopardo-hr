<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de l'émission/renouvellement de licence Edge.
 *
 * Issue #3319 : la route POST /api/v1/edge/{nodeId}/license était ouverte à
 * tout employé authentifié avec un valid_days non borné (auto-extension
 * arbitraire de la validité). L'autorisation exige un manager (défense en
 * profondeur : le middleware api.manager est aussi posé sur la route) et
 * valid_days est borné à 1..3650 jours.
 */
class IssueLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null && method_exists($actor, 'isManager') && $actor->isManager();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function messages(): array
    {
        return [
            'valid_days.min' => 'The valid_days must be at least 1.',
            'valid_days.max' => 'The valid_days must not exceed 3650.',
        ];
    }
}
