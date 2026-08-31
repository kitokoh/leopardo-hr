<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un dossier d'admission (EDU-004, #5820 / EDU-010).
 * external_id → rejeu idempotent ; consentement requis pour conversion.
 */
class StoreEduAdmissionRequest extends FormRequest
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
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('edu_academic_years', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'campus_id' => [
                'nullable',
                'integer',
                Rule::exists('edu_campuses', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'applicant_first_name' => ['required', 'string', 'max:100'],
            'applicant_last_name' => ['required', 'string', 'max:100'],
            'applicant_email' => ['nullable', 'email', 'max:150'],
            'applicant_phone' => ['nullable', 'string', 'max:30'],
            'applicant_birth_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(EduAdmission::STATUSES)],
            'source' => ['nullable', 'string', 'max:50'],
            'external_id' => ['nullable', 'string', 'max:100'],
            'crm_contact_id' => ['nullable', 'string', 'max:64'],
            'consent_contact' => ['nullable', 'boolean'],
            'consented_at' => ['nullable', 'date'],
            'applied_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
