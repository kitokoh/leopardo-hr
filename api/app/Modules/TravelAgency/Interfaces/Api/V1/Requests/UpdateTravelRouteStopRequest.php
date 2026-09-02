<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-307 (#6037) — Mise à jour d'une étape ordonnée d'une route.
 *
 * Tous les champs sont optionnels. Le changement de `rank` peut déclencher
 * un réordonnancement (le contrôleur re-tri les étapes par rang après
 * mise à jour).
 */
class UpdateTravelRouteStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelRoutePolicy::update() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = currentCompany()->id;

        return [
            'city_id' => [
                'sometimes',
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
