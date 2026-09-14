<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une station-service (FUEL-011, #5805).
 *
 * `code` unique par tenant — aligné sur `SaveFuelStationRequest`, qui portait
 * déjà la règle alors que ce couple `Store`/`Update` ne l'avait pas : un code
 * dupliqué violait `fuel_stations_company_code_unique` et remontait en
 * `SQLSTATE[23505]` (500) au lieu d'un 422 exploitable.
 */
class StoreFuelStationRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('fuel_stations', 'code')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
