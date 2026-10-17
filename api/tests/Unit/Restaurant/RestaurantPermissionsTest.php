<?php

declare(strict_types=1);

namespace Tests\Unit\Restaurant;

use App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions;
use Tests\TestCase;

/**
 * REST-OS-4 (Mission Leopardo Restaurant OS) — Mapping des rôles autorisés
 * pour les permissions d'impression.
 *
 * Vérifie que les nouvelles permissions `restaurant.print_jobs.*` sont bien
 * mappées (et que l'inconnu reste fail-closed : aucun rôle autorisé).
 */
class RestaurantPermissionsTest extends TestCase
{
    public function test_print_jobs_manage_maps_to_management_roles(): void
    {
        $permissions = new RestaurantPermissions;

        $this->assertSame(
            ['principal', 'rh', 'manager'],
            $permissions->requiresManagerRoles(RestaurantPermissions::PRINT_JOBS_MANAGE),
        );
    }

    public function test_print_jobs_operate_maps_to_kitchen_and_service_roles(): void
    {
        $permissions = new RestaurantPermissions;

        $this->assertSame(
            ['principal', 'rh', 'manager', 'server', 'kitchen'],
            $permissions->requiresManagerRoles(RestaurantPermissions::PRINT_JOBS_OPERATE),
        );
    }

    public function test_unknown_permission_is_fail_closed(): void
    {
        $permissions = new RestaurantPermissions;

        $this->assertSame([], $permissions->requiresManagerRoles('restaurant.does_not_exist'));
    }
}
