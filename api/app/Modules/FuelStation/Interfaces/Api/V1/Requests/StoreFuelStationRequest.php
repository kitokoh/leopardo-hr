<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une station FuelStation (FUEL-011, #5805).
 *
 * Code unique par tenant ; timezone valide (UTC par défaut) ; currency
 * ISO 4217 optionnelle ; metadata chiffrée au repos (cast encrypted:array).
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
            'timezone' => ['nullable', 'string', 'max:64', 'timezone'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
