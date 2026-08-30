<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Signalement d'un incident (FUEL-010, #5804) — pompiste ou manager.
 */
class StoreFuelIncidentRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('fuel_stations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'equipment_type' => ['nullable', Rule::in(FuelIncident::EQUIPMENT_TYPES)],
            'equipment_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(FuelIncident::PRIORITIES)],
        ];
    }
}
