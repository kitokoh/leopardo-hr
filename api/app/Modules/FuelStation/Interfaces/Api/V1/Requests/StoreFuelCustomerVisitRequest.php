<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Visite client — FUEL-016 (#5810). idempotency_key optionnel → crédit de
 * fidélité unique par visite.
 */
class StoreFuelCustomerVisitRequest extends FormRequest
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
            'visited_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:160', Rule::unique('fuel_customer_visits', 'idempotency_key')],
        ];
    }
}
