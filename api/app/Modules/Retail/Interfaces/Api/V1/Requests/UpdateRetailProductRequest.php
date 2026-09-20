<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Support\RetailPricePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un produit du module Retail (BC-17 RETAIL, #7672).
 *
 * Slug et SKU uniques par tenant, hors produit courant.
 */
class UpdateRetailProductRequest extends FormRequest
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

        $product = $this->route('product');
        $productId = $product instanceof RetailProduct ? (int) $product->getKey() : (int) $product;
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
                    ->where('company_id', (string) $actor->company_id)
                    ->ignore($productId),
            ],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::unique('retail_products', 'sku')
                    ->where('company_id', (string) $actor->company_id)
                    ->ignore($productId),
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
            'image_url' => ['nullable', 'url', 'max:500'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
