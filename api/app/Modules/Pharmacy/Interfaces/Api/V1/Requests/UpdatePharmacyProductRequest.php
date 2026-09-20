<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un produit d'officine — PHARMA-002 (#7799). Unicité
 * code-barres/code interne par tenant en ignorant le produit courant.
 */
class UpdatePharmacyProductRequest extends FormRequest
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
        /** @var Employee|null $actor */
        $actor = $this->user();

        /** @var PharmacyProduct|null $product */
        $product = $this->route('product');
        $ignoredId = $product?->getAttribute('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'dci' => ['nullable', 'string', 'max:191'],
            'form' => ['nullable', 'string', 'max:100'],
            'dosage' => ['nullable', 'string', 'max:100'],
            'barcode' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('pharmacy_products', 'barcode')->ignore($ignoredId)->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'internal_code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('pharmacy_products', 'internal_code')->ignore($ignoredId)->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'category' => ['nullable', Rule::in(PharmacyProduct::CATEGORIES)],
            'unit' => ['nullable', 'string', 'max:30'],
            'prescription_required' => ['nullable', 'boolean'],
            'is_controlled' => ['nullable', 'boolean'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(PharmacyProduct::STATUSES)],
        ];
    }
}
