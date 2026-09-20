<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Models\TravelDistributorKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-DISTRIBUTION (#7641) — validation d'émission d'une clé distributeur.
 *
 * `scopes` est une allowlist fail-closed (`TravelDistributorKey::SCOPES`) :
 * un scope inconnu est refusé en 422, jamais ignoré silencieusement.
 */
class StoreTravelDistributorKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelDistributorKeyPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(TravelDistributorKey::SCOPES)],
        ];
    }
}
