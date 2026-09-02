<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-813 (#6103) — Mise à jour d'une politique d'annulation
 * (mêmes bornes que la création).
 */
class UpdateTravelCancellationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelCancellationPolicyPolicy::update() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trip_id' => ['nullable', 'integer', 'exists:travel_trips,id'],
            'class_id' => ['nullable', 'integer', 'exists:travel_classes,id'],
            'cancel_before_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'penalty_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'refundable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
