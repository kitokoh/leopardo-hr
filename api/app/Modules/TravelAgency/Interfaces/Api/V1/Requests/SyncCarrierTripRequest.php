<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-807 (#6086) — Synchronisation d'un trajet transporteur (API
 * entrante, jeton `X-Carrier-Token`).
 *
 * Validation stricte : clé externe obligatoire, bornes sur les sièges et
 * les tarifs, dates valides. La rejeu est idempotent (upsert par
 * `external_id`).
 */
class SyncCarrierTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // L'authentification par jeton est faite dans le contrôleur.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'route_id' => ['nullable', 'integer', 'exists:travel_routes,id'],
            'route.external_id' => ['nullable', 'string', 'max:120'],
            'route.code' => ['nullable', 'string', 'max:40'],
            'route.origin_city_id' => ['required_with:route.external_id', 'integer', 'exists:travel_cities,id'],
            'route.destination_city_id' => ['required_with:route.external_id', 'integer', 'exists:travel_cities,id'],
            'departure_date' => ['required', 'date'],
            'departure_time' => ['nullable', 'date_format:H:i:s'],
            'arrival_date' => ['nullable', 'date'],
            'arrival_time' => ['nullable', 'date_format:H:i:s'],
            'means_of_transport' => ['nullable', 'string', 'max:30'],
            'total_seats' => ['required', 'integer', 'min:1', 'max:200'],
            'status' => ['nullable', 'string', 'max:20'],
            'prices' => ['nullable', 'array', 'max:10'],
            'prices.*.class_id' => ['nullable', 'integer', 'exists:travel_classes,id'],
            'prices.*.class_code' => ['nullable', 'string', 'max:40'],
            'prices.*.adult_price_minor' => ['required', 'integer', 'min:0'],
            'prices.*.child_price_minor' => ['nullable', 'integer', 'min:0'],
            'prices.*.currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
