<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / remplacement d'une station-service (FUEL-011, #5805).
 *
 * `code` unique par tenant. Sémantique PUT : tous les champs sont
 * obligatoires en écriture.
 */
class SaveFuelStationRequest extends FormRequest
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
                'required',
                'string',
                'max:40',
                Rule::unique('fuel_stations', 'code')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                )->ignore($stationId),
            ],
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:300'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['required', 'string', 'timezone'],
            'currency' => ['nullable', 'string', 'max:8'],
            'status' => ['required', Rule::in(FuelStation::STATUSES)],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
