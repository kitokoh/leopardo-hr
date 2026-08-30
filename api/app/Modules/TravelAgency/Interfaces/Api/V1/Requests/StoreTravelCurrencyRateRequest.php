<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-805 (#6096) — Création d'un taux de conversion par période.
 */
class StoreTravelCurrencyRateRequest extends FormRequest
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
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'different:from_currency', 'size:3'],
            'rate_minor' => ['required', 'integer', 'min:1', 'max:999999999'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }
}
