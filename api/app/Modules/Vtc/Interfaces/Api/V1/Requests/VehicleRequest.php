<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Requests;

use App\Modules\Vtc\Domain\Enums\VtcVehicleCategory;
use App\Modules\Vtc\Domain\Enums\VtcVehicleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création/mise à jour d'un véhicule VTC (BC-34 VTC, VTC-06/#8362).
 *
 * La plaque est unique par tenant (contrainte DB — un doublon est renvoyé
 * en 422 propre côté contrôleur, jamais une 500).
 */
final class VehicleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plate' => ['required', 'string', 'max:20'],
            'brand' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:40'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:60'],
            'category' => ['nullable', 'string', Rule::in(VtcVehicleCategory::values())],
            'status' => ['nullable', 'string', Rule::in(VtcVehicleStatus::values())],
        ];
    }
}
