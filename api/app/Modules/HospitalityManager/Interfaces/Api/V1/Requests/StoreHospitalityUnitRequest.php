<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une unité physique dans un établissement — HOSP-002 (#7944).
 * Code unique par (tenant, établissement) ; le type rattaché doit appartenir
 * au MÊME établissement (cross-établissement refusé en validation).
 */
class StoreHospitalityUnitRequest extends FormRequest
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

        /** @var HospitalityProperty|null $property */
        $property = $this->route('property');

        return [
            'room_type_id' => [
                'nullable',
                'integer',
                Rule::exists('hospitality_room_types', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('property_id', $property?->getKey())
                ),
            ],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('hospitality_units', 'code')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('property_id', $property?->getKey())
                ),
            ],
            'floor' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::in(HospitalityUnit::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
