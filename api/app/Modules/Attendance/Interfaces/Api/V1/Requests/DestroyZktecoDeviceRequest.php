<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue #2232 — DELETE /zkteco/devices/{id} supprimait un terminal sans
 * valider le paramètre de route. `id` doit être un entier strictement
 * positif → 422 sur 0/négatif au lieu d'un accès base incohérent.
 */
class DestroyZktecoDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function validationData(): array
    {
        return [
            'id' => $this->route('id'),
        ];
    }
}
