<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelTankStockLevel;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'un niveau de stock de cuve (FUEL-009, #5803).
 *
 * La cuve est validée tenant-scopée (FK composite anti cross-tenant) ; le
 * rejeu idempotent passe par `idempotency_key` (UNIQUE par tenant).
 */
class StoreFuelTankStockLevelRequest extends FormRequest
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
            'tank_id' => [
                'required',
                'integer',
                Rule::exists('fuel_tanks', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'recorded_on' => ['required', 'date', 'before_or_equal:today'],
            'level_minor' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
            'source_code' => ['nullable', Rule::in(FuelTankStockLevel::SOURCES)],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
