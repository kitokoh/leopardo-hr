<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-407 (#6194) — Validation stricte d'un encaissement de commande.
 *
 * `amount_minor` est vérifié par l'action contre le reste à payer calculé
 * serveur (montant client rejeté s'il diffère — critère d'acceptation).
 * `idempotency_key` (uuid) permet le rejeu sans doublon ; `tip_minor`
 * optionnel. L'autorisation est tranchée par
 * `RestaurantOrderPaymentPolicy::create()`.
 */
class PayRestaurantOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantOrderPaymentPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider_code' => ['required', 'string', Rule::in(['cash', 'card', 'mobile_money'])],
            'amount_minor' => ['required', 'integer', 'min:0', 'max:999999999'],
            'tip_minor' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }
}
