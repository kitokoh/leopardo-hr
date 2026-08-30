<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-309 (#6039) — Mise à jour d'un tarif par classe.
 *
 * Tous les champs sont optionnels ; `class_id` reste modifiable (l'unicité
 * (trip, classe) est contrôlée par la DB → 409).
 */
class UpdateTravelTripPriceRequest extends FormRequest
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
                'sometimes',
                'integer',
                Rule::exists('travel_classes', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $companyId)
                ),
            ],
            'adult_price_minor' => ['sometimes', 'integer', 'min:1'],
            'child_price_minor' => ['nullable', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ];
    }
}
