<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-416 (#6068) — formulaire de contact (bornes + consentement).
 *
 * `consent` : required + accepted → aucune captation sans consentement
 * explicite (RGPD) ; `message` borné à 2000 caractères (anti-spam).
 */
class StoreTravelContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:2000'],
            'consent' => ['required', 'accepted'],
        ];
    }
}
