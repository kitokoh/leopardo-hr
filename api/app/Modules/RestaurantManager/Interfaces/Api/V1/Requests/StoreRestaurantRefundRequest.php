<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-408 (#6195) — Validation stricte d'un remboursement.
 *
 * `amount_minor` est vérifié par l'action contre le montant encore
 * remboursable (double remboursement impossible) ; `reason_code` fait partie
 * d'un référentiel fixe ; `idempotency_key` (uuid) rend le rejeu inoffensif.
 * L'autorisation est tranchée par `RestaurantRefundPolicy::create()`
 * (réservé `restaurant.manage`).
 */
class StoreRestaurantRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantRefundPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1', 'max:999999999'],
            'reason_code' => ['required', 'string', Rule::in(['customer_request', 'wrong_item', 'quality_issue', 'no_show', 'duplicate_charge', 'other'])],
            'reason_text' => ['nullable', 'string', 'max:1000'],
            'payment_id' => ['nullable', 'integer'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }
}
