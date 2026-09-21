<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un établissement — HOSP-002 (#7944). Le code reste unique
 * par tenant (l'enregistrement courant est ignoré).
 */
class UpdateHospitalityPropertyRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:40',
                Rule::unique('hospitality_properties', 'code')
                    ->where(fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id))
                    ->ignore($property?->getKey()),
            ],
            'type' => ['sometimes', 'required', Rule::in(HospitalityProperty::TYPES)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['sometimes', 'required', 'string', 'size:2'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'star_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'amenities' => ['nullable', 'array'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', Rule::in(HospitalityProperty::STATUSES)],
        ];
    }
}
