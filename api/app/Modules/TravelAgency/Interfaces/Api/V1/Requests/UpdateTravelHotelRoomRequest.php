<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-321 (#6051) — Validation de mise à jour d'une chambre d'hôtel.
 *
 * Tous les champs sont optionnels (PUT partiel, convention du module).
 * `hotel_id` n'est pas un champ : il est lié par la route.
 */
class UpdateTravelHotelRoomRequest extends FormRequest
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
            'type_code' => ['sometimes', 'string', 'max:40'],
            'room_number' => ['sometimes', 'string', 'max:20'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'price_per_night_minor' => ['sometimes', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
