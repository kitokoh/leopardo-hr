<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Signalement d'un incident équipement (FUEL-010, issue #5804).
 *
 * `description_redacted` : texte libre redacted (pas de PII, pas de secrets)
 * — la valeur est bornée et nettoyée côté service. Pièces jointes : seules
 * les MÉTADONNÉES contrôlées sont acceptées (nom, taille, mime) — jamais le
 * contenu du fichier (upload dédié hors périmètre de cette route).
 */
class StoreFuelIncidentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'station_id' => ['nullable', 'integer', 'exists:fuel_stations,id'],
            'category' => ['nullable', Rule::in(FuelIncident::CATEGORIES)],
            'severity' => ['nullable', Rule::in(FuelIncident::SEVERITIES)],
            'description_redacted' => ['required', 'string', 'max:2000'],
            'attachments_metadata' => ['nullable', 'array', 'max:5'],
            'attachments_metadata.*.name' => ['required_with:attachments_metadata', 'string', 'max:255'],
            'attachments_metadata.*.size_bytes' => ['required_with:attachments_metadata', 'integer', 'min:1', 'max:10485760'],
            'attachments_metadata.*.mime' => ['required_with:attachments_metadata', 'string', 'max:100', 'regex:/^(image|application|text)\/[a-z0-9.+-]+$/i'],
            'external_id' => ['nullable', 'string', 'max:120'],
        ];
    }
}
