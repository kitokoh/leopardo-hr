<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ingestion d'une position chauffeur (BC-34 VTC, VTC-05/#8361).
 *
 * Contrat `POST /v1/vtc/driver/position` : `{lat, lng, recorded_at?,
 * source?}` — idempotence portée par (driver_id, recorded_at) ; défaut
 * `recorded_at` = maintenant. Bornes WGS 84 strictes.
 */
final class DriverPositionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'recorded_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'in:app,traccar'],
        ];
    }
}
