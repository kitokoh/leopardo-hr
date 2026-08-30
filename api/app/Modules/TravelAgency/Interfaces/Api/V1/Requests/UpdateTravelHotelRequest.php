<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-321 (#6051) — Validation de mise à jour d'un hôtel du catalogue.
 *
 * Tous les champs sont optionnels (PUT partiel, convention du module) ;
 * la ville référencée doit appartenir au même tenant.
 */
class UpdateTravelHotelRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:160'],
            'city_id' => ['sometimes', 'integer', Rule::exists('travel_cities', 'id')->where(
                fn (Builder $query): Builder => $query->where('company_id', currentCompany()->id)
            )],
            'classification' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'description_redacted' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
