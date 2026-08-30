<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-811 (#6101) — Création d'une récompense (catalogue tenant).
 */
class StoreLoyaltyRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission travel.manage tranchée dans le contrôleur.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'points_cost' => ['required', 'integer', 'min:1', 'max:1000000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
