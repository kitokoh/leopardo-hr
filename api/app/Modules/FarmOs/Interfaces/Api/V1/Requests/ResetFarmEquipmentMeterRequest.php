<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Remplacement explicite du compteur (changement de compteur, correction) —
 * la raison est OBLIGATOIRE : un compteur ne se remet pas a zero sans motif
 * trace (#9732).
 */
final class ResetFarmEquipmentMeterRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:200'],
            'client_usage_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
