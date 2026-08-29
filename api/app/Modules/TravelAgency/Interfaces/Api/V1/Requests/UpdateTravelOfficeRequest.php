<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Models\TravelCity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-303 (#6033) — Validation stricte de modification d'un bureau de vente.
 */
class UpdateTravelOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelOfficePolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'city_id' => ['sometimes', 'integer', Rule::exists((new TravelCity)->getTable(), 'id')],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
