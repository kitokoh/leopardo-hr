<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\Exceptions\ToolPermissionDeniedException;
use App\Core\Auth\Domain\Models\Employee;

/**
 * BC-23-D05 (issue #6237) — matrice de permissions par outil AI, versionnée
 * dans `config/ai.php` (`ai.tool_permissions` + `ai.role_permissions`).
 *
 * Vérifie, au moment de l'EXÉCUTION d'un tool (lecture comme écriture), que
 * le demandeur remplit :
 * 1. le rôle minimal requis (hiérarchie employee < manager < admin < super_admin) ;
 * 2. les permissions requises (accordées par rôle via `ai.role_permissions`).
 *
 * Comportement fail-closed : `assertCanUse()` lève une
 * `ToolPermissionDeniedException` (code stable `AI_TOOL_PERMISSION_DENIED`) ;
 * l'IntentEngine la traduit en refus explicite sans effet de bord.
 */
class ToolPermissionPolicy
{
    /** @var array<string, int> */
    private const ROLE_HIERARCHY = [
        'employee' => 1,
        'manager' => 2,
        'admin' => 3,
        'super_admin' => 4,
    ];

    /**
     * @return array{role: string, permissions: list<string>}|null
     */
    public function matrixFor(string $toolName): ?array
    {
        /** @var array<string, array{role: string, permissions: list<string>}> $matrix */
        $matrix = config('ai.tool_permissions', []);

        $entry = $matrix[$toolName] ?? null;
        if (! is_array($entry)) {
            return null;
        }

        return [
            'role' => (string) ($entry['role'] ?? 'employee'),
            'permissions' => array_values(array_filter(array_map(
                static fn (mixed $permission): string => is_scalar($permission) ? (string) $permission : '',
                $entry['permissions'] ?? [],
            ))),
        ];
    }

    /**
     * @return list<string>
     */
    public function permissionsForRole(string $role): array
    {
        /** @var array<string, list<string>> $rolePermissions */
        $rolePermissions = config('ai.role_permissions', []);

        return array_values(array_filter(array_map(
            static fn (mixed $permission): string => is_scalar($permission) ? (string) $permission : '',
            $rolePermissions[$role] ?? [],
        )));
    }

    public function canUse(string $toolName, string $role): bool
    {
        $matrix = $this->matrixFor($toolName);
        if ($matrix === null) {
            // Outil inconnu de la matrice : refus par défaut (fail-closed).
            return false;
        }

        if (! $this->roleSatisfies($role, $matrix['role'])) {
            return false;
        }

        $granted = $this->permissionsForRole($role);
        foreach ($matrix['permissions'] as $required) {
            if (! in_array($required, $granted, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws ToolPermissionDeniedException
     */
    public function assertCanUse(string $toolName, string $role): void
    {
        if ($this->canUse($toolName, $role)) {
            return;
        }

        $matrix = $this->matrixFor($toolName);

        throw new ToolPermissionDeniedException(
            sprintf(
                'AI tool "%s" denied for role "%s" (required role "%s", required permissions [%s]).',
                $toolName,
                $role,
                $matrix['role'] ?? 'unknown',
                implode(', ', $matrix['permissions'] ?? []),
            )
        );
    }

    /**
     * Résout le rôle AI d'un employé (miroir de la résolution de
     * l'Orchestrator : manager > employee).
     */
    public function resolveRole(int $userId, string $companyId): string
    {
        $employee = Employee::query()
            ->where('id', $userId)
            ->where('company_id', $companyId)
            ->first();

        if ($employee !== null && $employee->isManager()) {
            return 'manager';
        }

        return 'employee';
    }

    private function roleSatisfies(string $role, string $requiredRole): bool
    {
        $roleLevel = self::ROLE_HIERARCHY[$role] ?? 1;
        $requiredLevel = self::ROLE_HIERARCHY[$requiredRole] ?? 1;

        return $roleLevel >= $requiredLevel;
    }
}
