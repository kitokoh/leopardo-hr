<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'une livraison de carburant (FUEL-009, #5803).
 *
 * `reference` UNIQUE par tenant → rejeu idempotent. Station et cuve
 * validées tenant-scopées (FKs composites).
 */
class StoreFuelStockDeliveryRequest extends FormRequest
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

        return [
            'station_id' => [
                'required',
                'integer',
                Rule::exists('fuel_stations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'tank_id' => [
                'nullable',
                'integer',
                Rule::exists('fuel_tanks', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'product_code' => ['required', 'string', 'max:40'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'quantity_minor' => ['required', 'integer', 'gt:0', 'max:9007199254740991'],
            'unit_code' => ['nullable', Rule::in(['l', 'gal'])],
            'delivered_at' => ['nullable', 'date'],
            'reference' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
