<?php

declare(strict_types=1);

namespace Tests\Unit\Restaurant;

use App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions;
use Tests\TestCase;

/**
 * REST-OS-4 (Mission Leopardo Restaurant OS) — Mapping des rôles autorisés
 * pour les permissions d'impression.
 *
 * Vérifie que les permissions `restaurant.print_jobs.*` sont bien mappées (et
 * que l'inconnu reste fail-closed : aucun rôle autorisé).
 *
 * AUDIT PHASE 1 (2026-10-21) — attentes ALIGNÉES sur REST-OS-6 (#8642).
 * Ce test affirmait `['principal','rh','manager']` et
 * `['principal','rh','manager','server','kitchen']`, alors que #8642 a
 * délibérément ramené les deux permissions à `['principal','rh']` :
 * `manager` / `server` / `kitchen` ne sont PAS des valeurs assignables de
 * `manager_role` (enum réel : principal, rh, dept, comptable, superviseur,
 * marketing), donc les passer à `hasManagerRole()` produit un motif MORT —
 * toujours faux. Le garde CI `dev-hub/tools/check-manager-role-enum.sh`
 * (#7600) REFUSE explicitement ces littéraux, et il passe sur le code actuel.
 *
 * Le test était donc le côté périmé, et « réparer » le produit pour le
 * satisfaire aurait réintroduit le motif mort que #8642 a supprimé. Le besoin
 * « le serveur/cuisine peut imprimer » s'exprime par une assignation de
 * ressource (`employee_resource_assignments`, épique #7597), pas par un rôle
 * de `manager_role` : le scénario opérationnel reste couvert.
 */
class RestaurantPermissionsTest extends TestCase
{
    public function test_print_jobs_manage_maps_to_management_roles(): void
    {
        $permissions = new RestaurantPermissions;

        $this->assertSame(
            ['principal', 'rh'],
            $permissions->requiresManagerRoles(RestaurantPermissions::PRINT_JOBS_MANAGE),
        );
    }

    public function test_print_jobs_operate_maps_to_operational_roles(): void
    {
        $permissions = new RestaurantPermissions;

        // REST-OS-6 (#8642) : même ensemble que MANAGE — la distinction
        // opérationnelle passe par l'assignation de ressource, pas par un
        // `manager_role` inexistant.
        $this->assertSame(
            ['principal', 'rh'],
            $permissions->requiresManagerRoles(RestaurantPermissions::PRINT_JOBS_OPERATE),
        );
    }

    /**
     * Non-régression nommée de REST-OS-6 : aucune valeur métier non
     * assignable ne doit réapparaître dans un mapping de permission.
     */
    public function test_no_non_assignable_manager_role_is_mapped(): void
    {
        $permissions = new RestaurantPermissions;

        $assignable = ['principal', 'rh', 'dept', 'comptable', 'superviseur', 'marketing'];

        foreach ([RestaurantPermissions::PRINT_JOBS_MANAGE, RestaurantPermissions::PRINT_JOBS_OPERATE] as $permission) {
            foreach ($permissions->requiresManagerRoles($permission) as $role) {
                $this->assertContains(
                    $role,
                    $assignable,
                    sprintf(
                        "`%s` mappe le rôle `%s`, absent de l'enum assignable de `manager_role` : "
                        .'motif mort refusé par check-manager-role-enum.sh (#7600) et supprimé par #8642.',
                        $permission,
                        $role
                    )
                );
            }
        }
    }

    public function test_unknown_permission_is_fail_closed(): void
    {
        $permissions = new RestaurantPermissions;

        $this->assertSame([], $permissions->requiresManagerRoles('restaurant.does_not_exist'));
    }
}
