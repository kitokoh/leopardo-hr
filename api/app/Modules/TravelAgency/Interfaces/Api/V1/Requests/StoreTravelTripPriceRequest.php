<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-309 (#6039) — Création d'un tarif par classe sur un trajet.
 *
 * Montants en unités mineures strictement positifs, devise sur 3 caractères,
 * classe du même tenant. L'unicité (trip, classe) est portée par la
 * contrainte DB → 409 propre côté contrôleur.
 */
class StoreTravelTripPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelTripPolicy::update() tranche (sous-ressource du trajet)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = currentCompany()->id;

        return [
            'class_id' => [
                'required',
                'integer',
                Rule::exists('travel_classes', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $companyId)
                ),
            ],
            'adult_price_minor' => ['required', 'integer', 'min:1'],
            'child_price_minor' => ['nullable', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
