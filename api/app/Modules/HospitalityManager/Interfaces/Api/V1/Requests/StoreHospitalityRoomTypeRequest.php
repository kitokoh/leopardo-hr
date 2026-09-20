<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un type de chambre dans un établissement — HOSP-002 (#7944).
 * Code unique par (tenant, établissement).
 */
class StoreHospitalityRoomTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('hospitality_room_types', 'code')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('property_id', $property?->getKey())
                ),
            ],
            'description' => ['nullable', 'string'],
            'capacity_adults' => ['nullable', 'integer', 'min:0', 'max:30'],
            'capacity_children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'base_price_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'amenities' => ['nullable', 'array'],
            'status' => ['nullable', Rule::in(HospitalityRoomType::STATUSES)],
        ];
    }
}
