<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelIncidentAttachment;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Signalement d'un incident équipement (FUEL-010, #5804).
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
        ];
    }
}
