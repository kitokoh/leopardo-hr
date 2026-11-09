<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Modules\Retail\Domain\Enums\RetailPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Remboursement d'une commande POS Retail (BC-17 RETAIL, #9208).
 *
 * `amount_minor` strictement positif ; `reason` OBLIGATOIRE (un remboursement
 * sans motif est un trou d'audit) ; `method` restreint aux moyens reellement
 * utilises en caisse (`cash|card|mobile` — `online` passe par le
 * remboursement marketplace #7812). `restock_lines` optionnel : les articles
 * rendus remis en stock.
 */
class RefundRetailOrderRequest extends FormRequest
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
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'method' => ['required', 'string', Rule::in([
                RetailPaymentMethod::Cash->value,
                RetailPaymentMethod::Card->value,
                RetailPaymentMethod::Mobile->value,
            ])],
            'restock_lines' => ['nullable', 'array'],
            'restock_lines.*.product_id' => ['required', 'integer'],
            'restock_lines.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/^\d{1,9}(\.\d{1,3})?$/',
            ],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
