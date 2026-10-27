<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un bail locatif — HOSP-005 (#7947, BC-32).
 *
 * L'unité doit appartenir au MÊME établissement (et au même tenant) : un bail
 * qui référencerait l'unité d'un autre établissement ferait fuiter l'occupation
 * d'un bien d'autrui.
 *
 * `billing_day` est borné à 1..31 : la valeur est ensuite RAMENÉE au dernier
 * jour du mois quand il n'existe pas (cf. HospitalityRentService::dueDateFor).
 */
class StoreHospitalityLeaseRequest extends FormRequest
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
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('hospitality_units', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('property_id', $property?->getKey())
                ),
            ],
            'tenant_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'monthly_rent_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_day' => ['nullable', 'integer', 'between:1,31'],
            'deposit_minor' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
