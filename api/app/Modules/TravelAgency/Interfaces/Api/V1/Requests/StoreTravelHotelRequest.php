<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-321 (#6051) — Validation stricte de création d'un hôtel du
 * catalogue (classification 1-5 étoiles, statut whitelisté). La ville
 * référencée doit appartenir au même tenant (règle `exists` scopée
 * company_id).
 */
class StoreTravelHotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelHotelPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'city_id' => ['required', 'integer', Rule::exists('travel_cities', 'id')->where(
                fn (Builder $query): Builder => $query->where('company_id', currentCompany()->id)
            )],
            'classification' => ['required', 'integer', 'min:1', 'max:5'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'description_redacted' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
