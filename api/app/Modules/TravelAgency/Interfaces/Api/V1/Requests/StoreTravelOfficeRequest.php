<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Models\TravelCity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-303 (#6033) — Validation stricte de création d'un bureau de vente.
 *
 * `city_id` doit référencer une ville du tenant courant (scope
 * `BelongsToCompany` de `TravelCity`).
 */
class StoreTravelOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelOfficePolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'city_id' => ['required', 'integer', Rule::exists((new TravelCity)->getTable(), 'id')],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
