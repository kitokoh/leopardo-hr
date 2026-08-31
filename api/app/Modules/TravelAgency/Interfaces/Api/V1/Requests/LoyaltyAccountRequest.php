<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-811 (#6101) — Opérations sur le compte de fidélité d'un contact
 * (opt-in/opt-out, consultation du solde et des entrées).
 */
class LoyaltyAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Périmètre borné par le contrôleur (tenant).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_identifier' => ['required', 'string', 'max:255'],
        ];
    }
}
