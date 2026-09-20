<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une réservation — HOSP-004 (#7946). Champs éditables
 * uniquement (le statut ne change QUE par les transitions dédiées) ;
 * tout changement d'intervalle ou de type re-vérifie la disponibilité.
 */
class UpdateHospitalityReservationRequest extends FormRequest
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
        $companyId = $actor?->company_id;

        /** @var HospitalityReservation|null $reservation */
        $reservation = $this->route('reservation');
        $propertyId = $reservation?->getAttribute('property_id');

        return [
            'room_type_id' => [
                'sometimes',
                'integer',
                Rule::exists('hospitality_room_types', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $companyId)
                        ->where('property_id', $propertyId)
                ),
            ],
            'unit_id' => [
                'nullable',
                'integer',
                Rule::exists('hospitality_units', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $companyId)
                        ->where('property_id', $propertyId)
                ),
            ],
            'guest_name' => ['sometimes', 'required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'check_in' => ['sometimes', 'required', 'date'],
            'check_out' => ['sometimes', 'required', 'date'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:30'],
            'children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'total_amount_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
