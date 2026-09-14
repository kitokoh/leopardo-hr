<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une station-service (FUEL-011, #5805).
 *
 * `code` unique par tenant, la station éditée étant ignorée — aligné sur
 * `SaveFuelStationRequest` (cf. `StoreFuelStationRequest` pour le contexte).
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
        $stationId = $this->route('station');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:40',
                Rule::unique('fuel_stations', 'code')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                )->ignore($stationId),
            ],
            'name' => ['sometimes', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['sometimes', 'in:active,inactive,archived'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
