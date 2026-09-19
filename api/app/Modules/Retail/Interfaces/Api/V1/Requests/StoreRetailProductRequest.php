<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use App\Modules\Retail\Domain\Support\RetailPricePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un produit du module Retail (BC-17 RETAIL, #7672).
 *
 * Prix de vente en minor units (entier ≥ 0) + devise ISO 4217 — jamais de
 * flottants (pattern Catalog #6880). Slug unique par tenant (généré
 * automatiquement depuis le nom si absent), SKU requis et unique par
 * tenant. Statut par défaut `draft` (rien n'est vendable sans publication
 * explicite).
 */
class StoreRetailProductRequest extends FormRequest
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

        $statuses = array_map(
            static fn (RetailProductStatus $s): string => $s->value,
            RetailProductStatus::cases()
        );

        return [
            'name' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable',
                'alpha_dash',
                'max:220',
                Rule::unique('retail_products', 'slug')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'sku' => [
                'required',
                'string',
                'max:64',
                Rule::unique('retail_products', 'sku')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'barcode' => ['nullable', 'string', 'max:64'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('retail_categories', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'price_minor' => ['required', 'integer', 'min:0', 'max:9223372036854775807'],
            'cost_minor' => ['nullable', 'integer', 'min:0', 'max:9223372036854775807'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', Rule::in(RetailPricePolicy::allowedCurrencies())],
            'unit' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::in($statuses)],
            'meta' => ['nullable', 'array'],
        ];
    }
}
