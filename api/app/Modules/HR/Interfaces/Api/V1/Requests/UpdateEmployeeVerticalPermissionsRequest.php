<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\Contracts\VerticalPermissionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #EVAL — corps de `PUT /v1/employees/{id}/vertical-permissions`.
 *
 * Même doctrine que `UpdateEmployeeModuleGrantsRequest` (PUT = remplacement du
 * jeu COMPLET, seule forme qui rende la révocation possible en un geste), mais
 * le registre n'est PAS `ModuleKey` : il est DYNAMIQUE et borné au tenant —
 * seules les permissions des solutions RÉELLEMENT ACTIVES sont acceptées.
 *
 * Fail-closed : une clé inconnue, une permission d'une verticale inactive ou
 * une permission horizontale (`marketing`, …) est refusée en 422, jamais
 * ignorée silencieusement.
 */
class UpdateEmployeeVerticalPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy `manageModuleGrants` tranchée par le contrôleur
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // `company_id` n'existe que sur Employee : le type de retour de
        // `user()` couvre aussi User/SuperAdmin, qui n'ont pas cette colonne.
        // On lit donc la valeur sur l'instance Employee après vérification.
        $user = $this->user();
        $companyId = $user instanceof Employee ? $user->company_id : null;

        $company = $companyId !== null
            ? Company::query()->find($companyId)
            : null;

        $delegable = $company !== null
            ? app(VerticalPermissionCatalogue::class)->codesForCompany($company)
            : [];

        return [
            'permission_keys' => ['present', 'array'],
            'permission_keys.*' => ['required', 'string', Rule::in($delegable)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'permission_keys.present' => __('errors.MODULE_KEYS_REQUIRED'),
            'permission_keys.*.in' => __('errors.VERTICAL_PERMISSION_UNKNOWN'),
        ];
    }

    /**
     * Jeu demandé, dédoublonné.
     *
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        /** @var array<int, string> $raw */
        $raw = $this->validated('permission_keys', []);

        return array_values(array_unique($raw));
    }
}
