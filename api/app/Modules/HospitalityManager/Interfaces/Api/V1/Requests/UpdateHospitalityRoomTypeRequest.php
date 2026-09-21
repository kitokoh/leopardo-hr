<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un type de chambre — HOSP-002 (#7944). Le code reste unique
 * par (tenant, établissement) — l'enregistrement courant est ignoré.
 */
class UpdateHospitalityRoomTypeRequest extends FormRequest
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

        /** @var HospitalityRoomType|null $roomType */
        $roomType = $this->route('roomType');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:40',
                Rule::unique('hospitality_room_types', 'code')
                    ->where(
                        fn (Builder $query): Builder => $query
                            ->where('company_id', $actor?->company_id)
                            ->where('property_id', $roomType?->getAttribute('property_id'))
                    )
                    ->ignore($roomType?->getKey()),
            ],
            'description' => ['nullable', 'string'],
            'capacity_adults' => ['nullable', 'integer', 'min:0', 'max:30'],
            'capacity_children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'base_price_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'amenities' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(HospitalityRoomType::STATUSES)],
        ];
    }
}
