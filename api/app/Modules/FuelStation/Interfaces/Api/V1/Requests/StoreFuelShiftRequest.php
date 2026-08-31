<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un shift FuelStation (FUEL-005, #5799).
 *
 * Le nom est unique par tenant ; les horaires sont au format H:i avec
 * end_time strictement après start_time (pas de shift chevauchant minuit
 * dans cette itération — documenté FUEL-005). `station_id` BIGINT tenant-
 * scopé (FK composite (station_id, company_id) → fuel_stations).
 */
class StoreFuelShiftRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('fuel_shifts', 'name')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'station_id' => [
                'nullable',
                'integer',
                Rule::exists('fuel_stations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
