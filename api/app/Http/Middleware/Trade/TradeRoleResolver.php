<?php

declare(strict_types=1);

namespace App\Http\Middleware\Trade;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Résolution des rôles métier de la verticale Trade (TRADE-001, issue #8476,
 * BC-36 TRADE) — deny-by-default, miroir des permissions du TradeManifest.
 *
 * Source de vérité unique des ensembles de `manager_role` (leçon #8185 :
 * la garde `EnsureTradeRoleMiddleware` consomme ce résolveur, aucune
 * duplication).
 *
 * Rôles (spec docs/specifications/VERTICAL_TRADE_OS.md §2) :
 *
 * - `admin`     : manager `principal` (propriétaire/exploitant du tenant) ;
 * - `purchase`  : sourcing — RFQ, invitations fournisseurs, réceptions,
 *                 commandes d'achat ;
 * - `sales`     : vente — offres, clients, commandes de vente, pro forma ;
 * - `logistics` : transport, documents, aide douanière, coûts rendus ;
 * - `reports`   : rapports et indicateurs de la verticale.
 *
 * Attribution :
 * - managers `principal` → tous les rôles ;
 * - managers `dept`/`superviseur` → purchase, sales, logistics, reports
 *   (encadrement opérationnel — jamais `admin`) ;
 * - managers `rh`/`comptable` → `reports` uniquement (lecture d'indicateurs,
 *   pas d'opération commerciale) ;
 * - employés (non-managers) → rôles portés par les grants
 *   `employee_module_grants` de codes `trade.*` (délégation BOS-013/#7761 —
 *   `Employee::hasModuleGrant`) ;
 * - tout autre profil → AUCUN rôle trade (deny-by-default).
 *
 * Ces rôles métier ne remplacent JAMAIS le RBAC plateforme
 * (`manager_role`, policies, `PlatformPermission`).
 */
final class TradeRoleResolver
{
    /** @var list<string> Rôles gérés (miroir des clés TradeManifest::permissions()). */
    public const SUPPORTED_ROLES = ['admin', 'purchase', 'sales', 'logistics', 'reports'];

    /** @var list<string> manager_role donnant le rôle `admin`. */
    public const ADMIN_MANAGER_ROLES = ['principal'];

    /** @var list<string> manager_role donnant les rôles opérationnels. */
    public const OPERATIONAL_MANAGER_ROLES = ['principal', 'dept', 'superviseur'];

    /** @var list<string> manager_role donnant le rôle `reports`. */
    public const REPORTS_MANAGER_ROLES = ['principal', 'rh', 'dept', 'comptable', 'superviseur'];

    /**
     * @return list<string>
     */
    public function rolesFor(Employee $employee): array
    {
        if (! $employee->isManager()) {
            return $this->grantRolesFor($employee);
        }

        $roles = [];

        if ($employee->hasManagerRole(...self::ADMIN_MANAGER_ROLES)) {
            $roles[] = 'admin';
        }

        if ($employee->hasManagerRole(...self::OPERATIONAL_MANAGER_ROLES)) {
            $roles[] = 'purchase';
            $roles[] = 'sales';
            $roles[] = 'logistics';
        }

        if ($employee->hasManagerRole(...self::REPORTS_MANAGER_ROLES)) {
            $roles[] = 'reports';
        }

        return array_values(array_unique($roles));
    }

    public function hasRole(Employee $employee, string $role): bool
    {
        return in_array($role, $this->rolesFor($employee), true);
    }

    /**
     * Rôles d'un employé non-manager : uniquement via grants explicites
     * `trade.<rôle>` (délégation posée par le principal ou installée à
     * l'activation — `SolutionPermissionInstaller`, BOS-013).
     *
     * @return list<string>
     */
    private function grantRolesFor(Employee $employee): array
    {
        $roles = [];

        foreach (self::SUPPORTED_ROLES as $role) {
            if ($employee->hasModuleGrant('trade.'.$role)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}
