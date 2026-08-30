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
 * Quantité en unités mineures entières strictement positive ; `external_id`
 * UNIQUE (company_id, external_id) → rejeu idempotent ; tank_id optionnel
 * FK composite tenant-scopée.
 */
class StoreFuelDeliveryRequest extends FormRequest
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
            'product_type' => ['required', 'string', 'max:40'],
            'quantity_minor' => ['required', 'integer', 'min:1'],
            'tank_id' => [
                'nullable',
                'integer',
                Rule::exists('fuel_tanks', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('station_id', $this->route('station'))
                ),
            ],
            'delivered_at' => ['nullable', 'date'],
            'source' => ['nullable', Rule::in(['manual', 'supplier', 'import'])],
            'status' => ['nullable', Rule::in(['draft', 'received', 'verified'])],
            'external_id' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('fuel_deliveries', 'external_id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
