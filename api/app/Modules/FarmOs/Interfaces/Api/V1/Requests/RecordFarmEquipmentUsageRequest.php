<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use App\Modules\FarmOs\Domain\Enums\FarmMeterSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Releve de compteur — saisie manuelle ou import, jamais une lecture
 * automatique (#9732).
 */
final class RecordFarmEquipmentUsageRequest extends FormRequest
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
        return [
            'meter_value' => ['required', 'numeric', 'min:0'],
            'meter_source' => ['nullable', 'string', Rule::in(FarmMeterSource::values())],
            'farm_operation_id' => ['nullable', 'integer'],
            'parcel_id' => ['nullable', 'integer'],
            'operator_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:200'],
            'client_usage_id' => ['nullable', 'string', 'max:80'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
