<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelIncidentAttachment;
use Illuminate\Database\Query\Builder;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Signalement d'un incident équipement (FUEL-010, #5804).
 * Signalement d'un incident FuelStation (FUEL-010, #5804).
 *
 * `idempotency_key` obligatoire (rejeu sûr). Station tenant-scopée (FK
 * composite (x, company_id) → fuel_stations).
 */
class StoreFuelIncidentRequest extends FormRequest
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
            'station_id' => [
                'nullable',
                'required',
                'integer',
                Rule::exists('fuel_stations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'equipment_type' => ['nullable', Rule::in(FuelIncident::EQUIPMENT_TYPES)],
            'equipment_id' => ['nullable', 'integer'],
            'severity' => ['nullable', Rule::in(FuelIncident::SEVERITIES)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
            // Pièces jointes contrôlées (métadonnées uniquement).
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*.file_name' => ['required', 'string', 'max:200'],
            'attachments.*.mime_type' => ['required', Rule::in(FuelIncidentAttachment::ALLOWED_MIME_TYPES)],
            'attachments.*.size_bytes' => ['required', 'integer', 'min:0', 'max:5242880'],
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
            'equipment_type' => ['required', Rule::in(FuelIncident::EQUIPMENT_TYPES)],
            'equipment_id' => ['nullable', 'integer'],
            'severity' => ['required', Rule::in(FuelIncident::SEVERITIES)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
