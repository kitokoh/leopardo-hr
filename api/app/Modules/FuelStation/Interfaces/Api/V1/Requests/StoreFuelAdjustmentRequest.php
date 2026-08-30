<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajustement de stock (FUEL-009, #5803).
 *
 * Raison OBLIGATOIRE (aucun ajustement silencieux) ; quantité signée non
 * nulle ; `idempotency_key` optionnel → rejeu sans doublon.
 */
class StoreFuelAdjustmentRequest extends FormRequest
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
            'quantity_minor' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
            'tank_id' => [
                'nullable',
                'integer',
                Rule::exists('fuel_tanks', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('station_id', $this->route('station'))
                ),
            ],
            'idempotency_key' => [
                'nullable',
                'string',
                'max:160',
                Rule::unique('fuel_stock_movements', 'idempotency_key')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
        ];
    }
}
