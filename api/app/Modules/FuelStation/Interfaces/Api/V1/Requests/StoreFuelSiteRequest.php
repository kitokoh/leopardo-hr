<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un site opérationnel FuelStation (FUEL-011, #5805).
 * Code unique par tenant et par station ; FK composite anti cross-tenant.
 */
class StoreFuelSiteRequest extends FormRequest
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
                'required',
                'string',
                'max:40',
                Rule::unique('fuel_sites', 'code')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('station') !== null) {
            $this->merge(['station_id' => (int) $this->route('station')]);
        }
    }
}
