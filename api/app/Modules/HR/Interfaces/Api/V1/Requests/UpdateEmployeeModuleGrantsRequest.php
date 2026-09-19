<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Enums\ModuleKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Issue #7761 — corps de `PUT /v1/employees/{id}/module-grants`.
 *
 * Le `PUT` remplace le **jeu complet** des modules délégués au collaborateur :
 * ce qui n'est pas envoyé est révoqué (même doctrine que le PUT
 * resource-assignments #7598, seule forme qui rende la révocation possible en
 * un geste).
 *
 * L'autorisation n'est pas ici mais dans le contrôleur (policy
 * `manageModuleGrants`) ; ce FormRequest ne valide que la forme et
 * l'appartenance au registre fermé `ModuleKey` (fail-closed : une clé hors
 * registre est refusée, jamais ignorée).
 */
class UpdateEmployeeModuleGrantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'module_keys' => ['present', 'array'],
            'module_keys.*' => ['required', 'string', Rule::in(ModuleKey::keys())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'module_keys.present' => __('errors.MODULE_KEYS_REQUIRED'),
            'module_keys.*.in' => __('errors.MODULE_KEY_UNKNOWN'),
        ];
    }

    /**
     * Jeu demandé, dédoublonné (la clé unique en base ferait pareil).
     *
     * @return list<string>
     */
    public function moduleKeys(): array
    {
        /** @var array<int, string> $raw */
        $raw = $this->validated('module_keys', []);

        return array_values(array_unique(array_map(
            static fn (string $key): string => $key,
            $raw,
        )));
    }
}
