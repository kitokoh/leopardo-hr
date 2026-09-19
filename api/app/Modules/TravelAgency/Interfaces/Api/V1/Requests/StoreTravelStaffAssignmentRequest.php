<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\TravelStaffRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #7638 (TRAVEL-STAFF) — validation de création d'une affectation.
 *
 * `employee_id` doit appartenir au tenant courant (référence par valeur —
 * la contrainte d'appartenance est validée ICI, pas par une FK, spec §685).
 * Scope : exactement UN de `office_id` / `trip_id`, chacun tenant-scopé.
 */
class StoreTravelStaffAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelStaffAssignmentPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where(
                fn (Builder $query): Builder => $query->where('company_id', currentCompany()->id)
            )],
            'role' => ['required', 'string', Rule::enum(TravelStaffRole::class)],
            'office_id' => [
                'required_without:trip_id',
                'prohibits:trip_id',
                'integer',
                Rule::exists('travel_offices', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', currentCompany()->id)
                ),
            ],
            'trip_id' => [
                'required_without:office_id',
                'integer',
                Rule::exists('travel_trips', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', currentCompany()->id)
                ),
            ],
        ];
    }
}
