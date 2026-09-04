<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / remplacement d'un compteur (register) (FUEL-011, #5805).
 *
 * La pompe est validée tenant-scopée (FK composite anti cross-tenant).
 * `precision_scale` (0..6) définit l'unité mineure des relevés.
 */
class SaveFuelMeterRegisterRequest extends FormRequest
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
            'pump_id' => [
                'required',
                'integer',
                Rule::exists('fuel_pumps', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'meter_code' => ['required', 'string', 'max:40'],
            'meter_type' => ['required', Rule::in(FuelMeterRegister::TYPES)],
            'product_code' => ['required', 'string', 'max:40'],
            'unit_code' => ['required', 'string', 'max:12'],
            'precision_scale' => ['required', 'integer', 'min:0', 'max:6'],
            'rollover_limit' => ['nullable', 'integer', 'gt:0'],
            'installed_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(FuelMeterRegister::STATUSES)],
        ];
    }
}
