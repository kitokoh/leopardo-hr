<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-807 (#6086) — Création d'une clé API transporteur.
 */
class StoreTravelPartnerKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'carrier_id' => ['required', 'integer', Rule::exists('travel_carriers', 'id')->where(
                fn ($query) => $query->where('company_id', currentCompany()->id),
            )],
            'label' => ['nullable', 'string', 'max:120'],
        ];
    }
}
