<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-811 (#6101) — Opt-in / opt-out fidélité.
 *
 * #7445 — la clé de contact est `contact_identifier` (string : email ou
 * téléphone normalisé), la SEULE colonne de contact que porte
 * `travel_loyalty_accounts`. La validation portait `contact_id` (entier),
 * colonne inexistante : toute demande valide en apparence finissait en 500
 * (`NOT NULL contact_identifier` / `Undefined column contact_id`).
 */
class StoreTravelLoyaltyOptInRequest extends FormRequest
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
            'contact_identifier' => ['required', 'string', 'max:255'],
        ];
    }
}
