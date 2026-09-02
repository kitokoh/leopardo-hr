<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\CarrierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-304 (#6034) — Validation stricte de création d'une compagnie de
 * transport (type whitelisté dans l'enum `CarrierType`).
 */
class StoreTravelCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelCarrierPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = array_column(CarrierType::cases(), 'value');

        return [
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['sometimes', 'string', Rule::in($types)],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'logo_asset_id' => ['nullable', 'integer'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
