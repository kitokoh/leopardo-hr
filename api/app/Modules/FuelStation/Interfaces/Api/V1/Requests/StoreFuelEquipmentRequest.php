<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un équipement (pompe, cuve ou compteur) — FUEL-011 (#5805).
 * `kind` détermine la table et les règles applicables :
 * pump → fuel_pumps ; tank → fuel_tanks ; meter → fuel_meter_registers.
 */
class StoreFuelEquipmentRequest extends FormRequest
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
            'kind' => ['required', 'in:pump,tank,meter'],
            'station_id' => [
                'required',
                'integer',
                Rule::exists('fuel_stations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'code' => ['required', 'string', 'max:40'],
            'status' => ['nullable', 'in:active,inactive,retired'],
            // Pompe
            'product_types' => ['nullable', 'array'],
            'product_types.*' => ['string', 'max:40'],
            // Cuve
            'product_type' => ['nullable', 'string', 'max:40'],
            'capacity_minor' => ['nullable', 'integer', 'gt:0'],
            'current_level_minor' => ['nullable', 'integer', 'min:0'],
            // Compteur
            'meter_code' => ['nullable', 'string', 'max:40'],
            'meter_type' => ['nullable', 'in:mechanical,electronic,main_totalizer,secondary_totalizer,test'],
            'unit_code' => ['nullable', 'in:l,gal'],
            'precision_scale' => ['nullable', 'integer', 'min:0', 'max:6'],
            'rollover_limit' => ['nullable', 'integer', 'min:0'],
            'installed_at' => ['nullable', 'date'],
        ];
    }
}
