<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-307 (#6037) — Validation stricte de création d'une route.
 *
 * L'origine et la destination doivent différer (contrainte DB aussi) et
 * référencer des villes du même tenant.
 */
class StoreTravelRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelRoutePolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_column(TravelRecordStatus::cases(), 'value');
        $companyId = currentCompany()->id;

        $cityExists = Rule::exists('travel_cities', 'id')->where(
            fn (Builder $query): Builder => $query->where('company_id', $companyId)
        );

        return [
            'code' => ['required', 'string', 'max:40'],
            'origin_city_id' => ['required', 'integer', $cityExists, 'different:destination_city_id'],
            'destination_city_id' => ['required', 'integer', $cityExists],
            'distance_km' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'duration_min' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'status' => ['sometimes', 'string', Rule::in($statuses)],
        ];
    }
}
