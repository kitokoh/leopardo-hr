<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bascule de disponibilité chauffeur (BC-34 VTC, VTC-05/#8361).
 *
 * Contrat `POST /v1/vtc/driver/availability` : `{available: boolean}` —
 * available → éligible au dispatch ; sinon offline. Les statuts `busy`
 * (course en cours) et `suspended` verrouillent la bascule (409).
 */
final class DriverAvailabilityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'available' => ['required', 'boolean'],
        ];
    }
}
