<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Core\Tenant\Infrastructure\Services\ResourceTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Issue #7598 (R1 de l'épique #7597) — corps de
 * `PUT /v1/employees/{id}/resource-assignments`.
 *
 * Le `PUT` remplace le **jeu complet** des accès ressource du collaborateur :
 * ce qui n'est pas envoyé est retiré. C'est ce qui rend la révocation possible
 * en un seul geste, et c'est ce que les tests vérifient.
 *
 * L'autorisation n'est pas ici mais dans le contrôleur (policy
 * `manageResourceAssignments`) ; ce FormRequest ne valide que la forme et
 * l'appartenance au registre des types connus (fail-closed : un type non
 * déclaré est refusé, jamais ignoré).
 */
class UpdateEmployeeResourceAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var ResourceTypeRegistry $registry */
        $registry = app(ResourceTypeRegistry::class);

        return [
            'assignments' => ['present', 'array'],
            'assignments.*.resource_type' => ['required', 'string', Rule::in($registry->keys())],
            'assignments.*.resource_id' => ['required', 'integer', 'min:1'],
            'assignments.*.access_level' => ['required', 'string', Rule::in(EmployeeResourceAssignment::ACCESS_LEVELS)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'assignments.present' => __('errors.RESOURCE_ASSIGNMENTS_REQUIRED'),
            'assignments.*.resource_type.in' => __('errors.RESOURCE_TYPE_UNKNOWN'),
            'assignments.*.access_level.in' => __('errors.RESOURCE_ACCESS_LEVEL_INVALID'),
        ];
    }

    /**
     * Jeu demandé, dédoublonné sur le couple (type, ressource) — la dernière
     * entrée gagne, comme le ferait la clé unique en base.
     *
     * @return list<array{resource_type: string, resource_id: int, access_level: string}>
     */
    public function assignments(): array
    {
        /** @var array<int, array{resource_type?: string, resource_id?: int|string, access_level?: string}> $raw */
        $raw = $this->validated('assignments', []);

        $byKey = [];
        foreach ($raw as $entry) {
            $type = (string) ($entry['resource_type'] ?? '');
            $id = (int) ($entry['resource_id'] ?? 0);
            $level = (string) ($entry['access_level'] ?? '');

            if ($type === '' || $id <= 0 || $level === '') {
                continue;
            }

            $byKey[$type.':'.$id] = [
                'resource_type' => $type,
                'resource_id' => $id,
                'access_level' => $level,
            ];
        }

        return array_values($byKey);
    }
}
