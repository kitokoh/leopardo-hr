<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-307 (#6037) — Création d'une étape ordonnée sur une route.
 *
 * Le `rank` est optionnel : s'il est absent, l'étape est ajoutée en fin de
 * route (rank = max existant + 1). La ville doit appartenir au même tenant
 * et ne pas déjà figurer sur la route (contraintes DB).
 */
class StoreTravelRouteStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelRoutePolicy::update() tranche (modification de route)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = currentCompany()->id;

        return [
            'city_id' => [
                'required',
                'integer',
                Rule::exists('travel_cities', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $companyId)
                ),
            ],
            'rank' => ['sometimes', 'integer', 'min:1'],
            'is_stopover' => ['sometimes', 'boolean'],
            'min_duration_min' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
