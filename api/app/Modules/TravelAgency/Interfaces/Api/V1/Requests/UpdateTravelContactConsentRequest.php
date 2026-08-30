<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-912 (#6417) — Mise à jour des consentements par canal d'un
 * contact voyageur (gestion). Au moins un canal requis ; opt-out horodaté.
 */
class UpdateTravelContactConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rôles gestion tranchés au controller (hasManagerRole)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email_consent' => ['nullable', 'boolean'],
            'sms_consent' => ['nullable', 'boolean'],
            'whatsapp_consent' => ['nullable', 'boolean'],
        ];
    }
}
