<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Models\TravelCity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-302 (#6032) — Validation stricte de création d'une gare/terminal.
 *
 * `city_id` doit référencer une ville du tenant courant (le scope
 * `BelongsToCompany` de `TravelCity` filtre automatiquement la requête
 * `exists`, garantissant qu'une ville d'un autre tenant est rejetée en 422
 * — la Policy gère par ailleurs le 404 sur la ressource elle-même).
 */
class StoreTravelStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelStationPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'city_id' => ['required', 'integer', Rule::exists((new TravelCity)->getTable(), 'id')],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'is_terminal' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
