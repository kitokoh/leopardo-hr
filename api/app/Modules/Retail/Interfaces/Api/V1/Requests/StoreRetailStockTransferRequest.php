<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transfert de stock inter-emplacements (BC-17 RETAIL, #9268).
 *
 * Les deux emplacements et le produit doivent exister DANS le tenant
 * (`Rule::exists` scope `company_id` — pas de fuite cross-tenant). La
 * difference source/destination et le stock suffisant sont verifies par
 * `RetailStockService::transfer()` (dans la transaction, sous verrou) : une
 * validation ici ne prouverait rien sur l'etat du stock au moment de
 * l'ecriture.
 */
class StoreRetailStockTransferRequest extends FormRequest
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

        $companyId = (string) $actor->company_id;

        return [
            'from_location_id' => [
                'required',
                'integer',
                Rule::exists('retail_locations', 'id')->where('company_id', $companyId),
            ],
            'to_location_id' => [
                'required',
                'integer',
                'different:from_location_id',
                Rule::exists('retail_locations', 'id')->where('company_id', $companyId),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('retail_products', 'id')->where('company_id', $companyId),
            ],
            'quantity' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/^\d{1,9}(\.\d{1,3})?$/',
            ],
            'unit_cost_minor' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
