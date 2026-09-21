<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une réservation au guichet — HOSP-004 (#7946).
 *
 * L'établissement, le type et l'unité doivent appartenir au MÊME tenant
 * (et l'unité / le type au MÊME établissement) — sinon 422.
 * `check_out > check_in` est exigé (aussi CHECK en base).
 */
class StoreHospitalityReservationRequest extends FormRequest
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

        return [
            'property_id' => [
                'required',
                'integer',
                Rule::exists('hospitality_properties', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $companyId)
                ),
            ],
            'room_type_id' => [
                'required',
                'integer',
                Rule::exists('hospitality_room_types', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $companyId)
                        ->where('property_id', $this->input('property_id'))
                ),
            ],
            'unit_id' => [
                'nullable',
                'integer',
                Rule::exists('hospitality_units', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $companyId)
                        ->where('property_id', $this->input('property_id'))
                ),
            ],
            'guest_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:30'],
            'children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'status' => ['nullable', Rule::in([
                HospitalityReservation::STATUS_PENDING,
                HospitalityReservation::STATUS_CONFIRMED,
            ])],
            'total_amount_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
