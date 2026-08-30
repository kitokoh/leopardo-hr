<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Émission d'un lien d'accès portail guardian — Issue #5829 (EDU-013).
 *
 * TTL borné [1, 30] jours (défaut 7) ; `purpose` borné à la liste
 * EduGuardianAccessLink::PURPOSES. Validation fail-closed : toute valeur
 * hors bornes → 422.
 */
class StoreEduGuardianAccessLinkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'purpose' => ['nullable', 'string', 'max:30'],
        ];
    }
}
