<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-306 (#6036) — Validation de mise à jour d'un véhicule de flotte.
 *
 * Tous les champs sont optionnels (PUT partiel, convention du module) ;
 * le carrier référencé doit appartenir au même tenant.
 */
class UpdateTravelVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelVehiclePolicy::update() tranche l'autorisation
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
            'registration_number' => ['nullable', 'string', 'max:40'],
            'seat_capacity' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'carrier_id' => [
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
