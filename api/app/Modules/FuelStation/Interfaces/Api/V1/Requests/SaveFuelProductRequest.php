<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / remplacement d'un produit du catalogue (FUEL-011, #5805).
 *
 * `code` unique par tenant (catalogue tenant-scoped).
 */
class SaveFuelProductRequest extends FormRequest
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
        $productId = $this->route('product');

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('fuel_products', 'code')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                )->ignore($productId),
            ],
            'name' => ['required', 'string', 'max:160'],
            'unit_code' => ['required', 'string', 'max:12'],
            'status' => ['required', Rule::in(FuelProduct::STATUSES)],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
