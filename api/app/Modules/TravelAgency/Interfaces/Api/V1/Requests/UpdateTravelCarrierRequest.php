<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\CarrierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-304 (#6034) — Validation stricte de modification d'une compagnie
 * de transport.
 */
class UpdateTravelCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelCarrierPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = array_column(CarrierType::cases(), 'value');

        return [
            'code' => ['sometimes', 'string', 'max:40'],
            'name' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', 'string', Rule::in($types)],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'logo_asset_id' => ['nullable', 'integer'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
