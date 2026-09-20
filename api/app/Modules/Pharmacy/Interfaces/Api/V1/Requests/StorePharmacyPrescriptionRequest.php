<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une ordonnance — PHARMA-006 (#7803). Référence unique par
 * tenant ; patient = PII santé (tenant-scopée).
 */
class StorePharmacyPrescriptionRequest extends FormRequest
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

        return [
            'prescriber_id' => ['required', 'integer', 'min:1'],
            'patient_name' => ['required', 'string', 'max:191'],
            'patient_contact' => ['nullable', 'string', 'max:191'],
            'prescribed_at' => ['required', 'date_format:Y-m-d'],
            'reference' => [
                'required',
                'string',
                'max:100',
                Rule::unique('pharmacy_prescriptions', 'reference')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
