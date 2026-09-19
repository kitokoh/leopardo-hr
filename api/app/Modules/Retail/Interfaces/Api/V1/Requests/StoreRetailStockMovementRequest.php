<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Enums\RetailStockReasonCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'un mouvement de stock du module Retail (BC-17 RETAIL, #7673).
 *
 * Emplacement et produit doivent exister DANS le tenant (Rule::exists scoped
 * company_id — pas de fuite cross-tenant). Delta signé non nul, 3 décimales
 * max ; reason_code contrôlé (RetailStockReasonCode).
 */
class StoreRetailStockMovementRequest extends FormRequest
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
        /** @var Employee $actor */
        $actor = $this->user();

        $reasons = array_map(
            static fn (RetailStockReasonCode $r): string => $r->value,
            RetailStockReasonCode::cases()
        );

        return [
            'location_id' => [
                'required',
                'integer',
                Rule::exists('retail_locations', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('retail_products', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'quantity_delta' => [
                'required',
                'numeric',
                'not_in:0',
                'regex:/^-?\d{1,9}(\.\d{1,3})?$/',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    // Delta strictement non nul (0.000 passe `not_in:0`).
                    if (is_numeric($value) && abs((float) $value) < 0.0005) {
                        $fail('validation.not_in')->translate();
                    }
                },
            ],
            'reason_code' => ['required', Rule::in($reasons)],
            'reference_type' => ['nullable', 'string', 'max:80'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
