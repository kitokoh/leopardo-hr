<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ouverture d'une session de caisse (FUEL-007, #5801).
 *
 * `station_id` BIGINT tenant-scopé (FK composite (station_id, company_id)
 * → fuel_stations) — la cohérence tenant est garantie par la FK composite.
 */
class OpenFuelCashSessionRequest extends FormRequest
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
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
