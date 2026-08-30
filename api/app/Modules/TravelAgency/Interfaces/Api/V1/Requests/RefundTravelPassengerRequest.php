<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-808 (#6098) — Remboursement partiel d'un passager.
 */
class RefundTravelPassengerRequest extends FormRequest
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
            'passenger_id' => ['required', 'integer', Rule::exists('travel_passengers', 'id')],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'refund_key' => ['required', 'string', 'max:255'],
        ];
    }
}
