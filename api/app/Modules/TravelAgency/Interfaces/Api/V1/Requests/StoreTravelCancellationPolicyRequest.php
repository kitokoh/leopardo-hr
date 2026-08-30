<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-813 (#6103) — Création/mise à jour d'une politique d'annulation.
 */
class StoreTravelCancellationPolicyRequest extends FormRequest
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
            'trip_id' => ['nullable', 'integer', Rule::exists('travel_trips', 'id')],
            'class_id' => ['nullable', 'integer', Rule::exists('travel_classes', 'id')],
            'hours_before_departure' => ['required', 'integer', 'min:0', 'max:8760'],
            'penalty_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'refundable' => ['sometimes', 'boolean'],
        ];
    }
}
