<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Modules\Retail\Domain\Enums\RetailPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Encaissement d'un paiement de commande POS Retail (BC-17 RETAIL, #7674).
 *
 * Methode controlee (cash|card|mobile|online — `online` reserve a la future
 * boutique e-commerce, aucune passerelle en v1). Montant en minor units
 * (entier >= 1). `idempotency_key` absorbe les doubles soumissions.
 * L'autorisation est tranchee par RetailOrderPolicy::pay().
 */
class StoreRetailOrderPaymentRequest extends FormRequest
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
        $methods = array_map(
            static fn (RetailPaymentMethod $m): string => $m->value,
            RetailPaymentMethod::cases()
        );

        return [
            'method' => ['required', Rule::in($methods)],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'reference' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
