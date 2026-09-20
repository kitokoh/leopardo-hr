<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une unité physique — HOSP-002 (#7944). Le code reste unique
 * par (tenant, établissement) ; le type rattaché doit appartenir au MÊME
 * établissement que l'unité.
 */
class UpdateHospitalityUnitRequest extends FormRequest
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

        /** @var HospitalityUnit|null $unit */
        $unit = $this->route('unit');

        return [
            'room_type_id' => [
                'nullable',
                'integer',
                Rule::exists('hospitality_room_types', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('property_id', $unit?->getAttribute('property_id'))
                ),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:40',
                Rule::unique('hospitality_units', 'code')
                    ->where(
                        fn (Builder $query): Builder => $query
                            ->where('company_id', $actor?->company_id)
                            ->where('property_id', $unit?->getAttribute('property_id'))
                    )
                    ->ignore($unit?->getKey()),
            ],
            'floor' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', Rule::in(HospitalityUnit::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
