<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-319 (#6049) — Validation stricte de création d'un véhicule de location.
 *
 * La ville et le carrier propriétaire référencés doivent appartenir au même
 * tenant (règles `exists` scopées company_id) ; prix journalier strictement
 * positif, statut whitelisté.
 */
class StoreTravelRentalVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelRentalVehiclePolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_column(TravelRecordStatus::cases(), 'value');
        $companyId = currentCompany()->id;

        return [
            'code' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:160'],
            'city_id' => [
                'required',
                'integer',
                Rule::exists('travel_cities', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $companyId)
                ),
            ],
            'price_per_day_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date'],
            'owner_carrier_id' => [
                'nullable',
                'integer',
                Rule::exists('travel_carriers', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $companyId)
                ),
            ],
            'status' => ['sometimes', 'string', Rule::in($statuses)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
