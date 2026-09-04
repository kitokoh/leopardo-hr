<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelStockEntry;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entrée de stock FuelStation (FUEL-009, #5803).
 *
 * `idempotency_key` rend le rejeu sûr (zéro doublon). Un ajustement
 * (entry_type = adjustment) exige un `reason` non vide — aucun ajustement
 * silencieux (règle FUEL-009).
 */
class StoreFuelStockEntryRequest extends FormRequest
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
                'nullable',
                'integer',
                Rule::exists('fuel_stations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'product_code' => ['required', 'string', 'max:40'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'entry_type' => ['nullable', Rule::in(FuelStockEntry::ENTRY_TYPES)],
            'reason' => ['required_if:entry_type,adjustment', 'nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
            'entry_date' => ['nullable', 'date'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
