<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\MeansOfTransport;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * TRAVEL-308 (#6038) — Validation de mise à jour d'un trajet daté.
 *
 * Tous les champs sont optionnels. `total_seats` reste modifiable tant que
 * le trajet n'est pas publié ; après publication, le workflow impose de
 * passer par `publish`/`cancel` (les Actions tranchent).
 */
class UpdateTravelTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelTripPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $means = array_column(MeansOfTransport::cases(), 'value');
        $statuses = array_column(TripStatus::cases(), 'value');
        $companyId = currentCompany()->id;

        $tenantExists = fn (string $table): Exists => Rule::exists($table, 'id')->where(
            fn (Builder $query): Builder => $query->where('company_id', $companyId)
        );

        return [
            'code' => ['sometimes', 'string', 'max:40'],
            'route_id' => ['sometimes', 'integer', $tenantExists('travel_routes')],
            'carrier_id' => ['nullable', 'integer', $tenantExists('travel_carriers')],
            'vehicle_id' => ['nullable', 'integer', $tenantExists('travel_vehicles')],
            'departure_date' => ['sometimes', 'date', 'after_or_equal:today'],
            'departure_time' => ['sometimes', 'date_format:H:i'],
            'arrival_date' => ['sometimes', 'date', 'after_or_equal:departure_date'],
            'arrival_time' => ['sometimes', 'date_format:H:i'],
            'means_of_transport' => ['sometimes', 'string', Rule::in($means)],
            'total_seats' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'status' => ['sometimes', 'string', Rule::in($statuses)],
        ];
    }
}
