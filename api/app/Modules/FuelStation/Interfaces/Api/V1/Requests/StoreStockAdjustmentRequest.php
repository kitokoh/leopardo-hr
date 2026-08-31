<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajustement de stock explicite FuelStation (FUEL-009, #5803).
 *
 * Aucun ajustement silencieux : motif (`notes`) obligatoire, mouvement
 * audité (created_by). `idempotency_key` obligatoire (rejeu sûr).
 */
class StoreStockAdjustmentRequest extends FormRequest
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
            'product_type' => ['required', 'string', 'max:40'],
            'quantity_minor' => ['required', 'integer', 'gt:0', 'max:999999999999'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'movement_at' => ['required', 'date'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
