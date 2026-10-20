<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un établissement (BOS-T-01 / #8624).
 *
 * Même allowlist que la création : un statut ou un code hors format ne peut
 * pas être écrit par l'API. L'unicité du code ignore l'établissement courant,
 * sinon sauvegarder sans changer le code renverrait 422.
 */
class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isManager();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }

        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->user();
        $companyId = $user instanceof Employee ? $user->company_id : null;
        $siteId = $this->route('site');

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => [
                'nullable', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('sites', 'code')
                    ->where(static fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($siteId),
            ],
            'site_type' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', Rule::in(Site::STATUSES)],
            'address' => ['nullable', 'string', 'max:500'],
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'gps_radius_m' => ['nullable', 'integer', 'min:10', 'max:5000'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.unique' => "Ce code d'établissement est déjà utilisé dans votre entreprise.",
            'status.in' => "Le statut doit être l'un de : actif, inactif, archivé.",
        ];
    }
}
