<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Rules\SupportedCountry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * MULTI-PAYS (#1867) — le pays d'un run de paie est VERROUILLÉ sur le pays
 * légal du tenant :
 *  - si `country_code` est absent, il est déduit du tenant (aucune saisie) ;
 *  - s'il est fourni et différent du pays du tenant → 422 (un client
 *    authentifié ne peut pas détourner le contexte pays du tenant).
 */
class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('country_code')) {
            $user = $this->user();
            $company = $user instanceof Employee ? $user->company : null;

            if ($company instanceof Company && $company->country !== '') {
                $this->merge(['country_code' => strtoupper((string) $company->country)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'country_code' => ['required', 'string', 'size:2', new SupportedCountry],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $company = $user instanceof Employee ? $user->company : null;
            $tenantCountry = $company instanceof Company ? strtoupper((string) $company->country) : null;

            if ($tenantCountry !== null && strtoupper((string) $this->input('country_code')) !== $tenantCountry) {
                $validator->errors()->add(
                    'country_code',
                    "Le pays du run doit correspondre au pays légal du tenant ({$tenantCountry})."
                );
            }
        });
    }
}
