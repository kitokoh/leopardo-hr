<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Requests;

use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création/mise à jour d'un chauffeur VTC (BC-34 VTC, VTC-06/#8362).
 *
 * `user_id` rattache la fiche à un compte employé du tenant (surface API
 * chauffeur, VTC-05) — l'existence du véhicule et du compte dans le tenant
 * est vérifiée côté contrôleur (422 propre).
 */
final class DriverRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', Rule::in(VtcDriverStatus::values())],
            'vehicle_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
