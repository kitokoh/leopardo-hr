<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-321 (#6051) — Validation stricte de création d'une chambre d'hôtel.
 *
 * `hotel_id` n'est pas un champ : il est lié par la route
 * (`travel_hotels/{hotel}/rooms`, 404 sûr cross-tenant géré par le
 * contrôleur). Capacité et tarif strictement positifs, devise ISO 4217
 * sur 3 caractères.
 */
class StoreTravelHotelRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelHotelPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type_code' => ['required', 'string', 'max:40'],
            'room_number' => ['required', 'string', 'max:20'],
            'capacity' => ['required', 'integer', 'min:1'],
            'price_per_night_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
