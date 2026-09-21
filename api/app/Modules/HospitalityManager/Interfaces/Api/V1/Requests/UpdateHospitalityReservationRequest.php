<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Mise à jour d'une réservation — HOSP-004 (#7946). Champs éditables
 * uniquement (le statut ne change QUE par les transitions dédiées) ;
 * tout changement d'intervalle ou de type re-vérifie la disponibilité.
 *
 * #8019 : l'unité affectée doit appartenir au MÊME type de chambre que la
 * réservation — invariant vérifié sur l'état EFFECTIF après mise à jour
 * (`room_type_id` et `unit_id` du payload s'ils sont fournis, sinon ceux de
 * la réservation) : une chambre « Standard » ne peut pas être affectée à une
 * réservation « Deluxe », y compris quand SEUL le type change.
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

    /**
     * Cohérence unité ↔ type de chambre sur l'état EFFECTIF (#8019).
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var HospitalityReservation|null $reservation */
                $reservation = $this->route('reservation');

                if (! $reservation instanceof HospitalityReservation) {
                    return;
                }

                // Seule une requête qui TOUCHE l'affectation (unité ou type)
                // est contrainte : une édition sans rapport (ex. `guest_name`)
                // reste possible sur un historique incohérent.
                if (! $this->exists('unit_id') && ! $this->exists('room_type_id')) {
                    return;
                }

                // Une erreur de référence (unité/type inconnu du tenant ou de
                // l'établissement) suffit : pas de message en doublon.
                if ($validator->errors()->has('unit_id') || $validator->errors()->has('room_type_id')) {
                    return;
                }

                $unitId = $this->exists('unit_id')
                    ? ($this->input('unit_id') !== null ? (int) $this->input('unit_id') : null)
                    : ($reservation->unit_id !== null ? (int) $reservation->unit_id : null);

                // Aucune unité affectée (ou unité explicitement retirée) :
                // aucun invariant à vérifier.
                if ($unitId === null) {
                    return;
                }

                $roomTypeId = $this->exists('room_type_id') && $this->input('room_type_id') !== null
                    ? (int) $this->input('room_type_id')
                    : (int) $reservation->room_type_id;

                /** @var Employee|null $actor */
                $actor = $this->user();

                $matches = HospitalityUnit::query()
                    ->where('company_id', $actor?->company_id)
                    ->whereKey($unitId)
                    ->where('room_type_id', $roomTypeId)
                    ->exists();

                if (! $matches) {
                    $validator->errors()->add('unit_id', __('hospitality.unit_room_type_mismatch'));
                }
            },
        ];
    }
}
