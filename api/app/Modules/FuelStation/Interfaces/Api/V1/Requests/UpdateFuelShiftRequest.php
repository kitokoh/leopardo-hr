<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un shift FuelStation (FUEL-005, #5799).
 *
 * Mêmes règles que la création, avec unicité du nom tenant-scoped en
 * s'ignorant soi-même.
 */
class UpdateFuelShiftRequest extends FormRequest
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

        /** @var FuelShift|null $shift */
        $shift = $this->route('shift');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('fuel_shifts', 'name')
                    ->where(fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id))
                    ->ignore($shift?->id),
            ],
            'station_id' => ['sometimes', 'nullable', 'uuid'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
