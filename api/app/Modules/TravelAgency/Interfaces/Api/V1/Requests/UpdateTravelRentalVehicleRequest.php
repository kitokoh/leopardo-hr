<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-319 (#6049) — Validation de mise à jour d'un véhicule de location.
 *
 * Tous les champs sont optionnels (PUT partiel, convention du module) ;
 * la ville et le carrier propriétaire référencés doivent appartenir au
 * même tenant.
 */
class UpdateTravelRentalVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelRentalVehiclePolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_column(TravelRecordStatus::cases(), 'value');
        $companyId = currentCompany()->id;

        return [
            'code' => ['sometimes', 'string', 'max:40'],
            'title' => ['sometimes', 'string', 'max:160'],
            'city_id' => [
                'sometimes',
                'integer',
                Rule::exists('travel_cities', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $companyId)
                ),
            ],
            'price_per_day_minor' => ['sometimes', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'available_from' => ['sometimes', 'nullable', 'date'],
            'available_until' => ['sometimes', 'nullable', 'date'],
            'owner_carrier_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('travel_carriers', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $companyId)
                ),
            ],
            'status' => ['sometimes', 'string', Rule::in($statuses)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
