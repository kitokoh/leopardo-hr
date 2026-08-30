<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une station FuelStation (FUEL-011, #5805).
 * Le code reste unique par tenant (le code courant est exclu de l'unicité).
 */
class UpdateFuelStationRequest extends FormRequest
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
        /** @var int|null $stationId */
        $stationId = $this->route('station');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:40',
                Rule::unique('fuel_stations', 'code')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                )->ignore($stationId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
