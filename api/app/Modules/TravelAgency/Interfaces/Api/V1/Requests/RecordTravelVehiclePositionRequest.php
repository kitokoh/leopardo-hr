<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #8540 (TRAVEL-OS) — transmission d'une position de véhicule par un
 * dispositif autorisé (boîtier GPS ou application).
 *
 * La source est OBLIGATOIRE et whitelistée : le serveur ne devine jamais
 * d'où vient une position, et une estimation ne peut pas être présentée
 * comme une mesure.
 *
 * #8548 — `recorded_at` (optionnel) porte l'horodatage de la MESURE côté
 * dispositif. Il est borné : une date dans le futur est refusée (une mesure
 * ne peut pas avoir eu lieu plus tard que maintenant), et une date trop
 * ancienne est refusée pour ne pas polluer l'historique avec des relevés
 * erronés. Absent, l'instant de réception fait foi.
 */
class RecordTravelVehiclePositionRequest extends FormRequest
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
        $sources = array_column(TravelVehiclePositionSource::cases(), 'value');

        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'source' => ['required', 'string', Rule::in($sources)],
            'recorded_at' => ['nullable', 'date', 'before_or_equal:now', 'after:-30 days'],
        ];
    }
}
