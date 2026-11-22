<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use App\Modules\FarmOs\Domain\Enums\FarmEquipmentKind;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentMeterType;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creation d'un materiel agricole (#9732).
 *
 * Extraite de `FarmEquipmentController::store()`. Les listes fermees viennent
 * des enums du domaine : une valeur ajoutee a l'enum est immediatement
 * acceptee, sans risque d'oubli dans une liste recopiee.
 */
final class StoreFarmEquipmentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:60'],
            'kind' => ['required', 'string', Rule::in(FarmEquipmentKind::values())],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'serial_number' => ['nullable', 'string', 'max:80'],
            'plate_number' => ['nullable', 'string', 'max:32'],
            'farm_site_id' => ['nullable', 'integer'],
            'acquired_on' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'meter_type' => ['nullable', 'string', Rule::in(FarmEquipmentMeterType::values())],
            'meter_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(FarmEquipmentStatus::values())],
            'assigned_team_id' => ['nullable', 'integer'],
            'assigned_employee_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_equipment_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
