<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une pompe FuelStation (FUEL-011, #5805).
 * Code unique par (tenant, station) ; product_types = liste de codes du
 * catalogue `fuel_products` du tenant.
 */
class StoreFuelPumpRequest extends FormRequest
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
                Rule::unique('fuel_pumps', 'code')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('station_id', $this->route('station'))
                ),
            ],
            'product_types' => ['nullable', 'array', 'max:20'],
            'product_types.*' => ['string', 'max:40'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'retired'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('station') !== null) {
            $this->merge(['station_id' => (int) $this->route('station')]);
        }
    }
}
