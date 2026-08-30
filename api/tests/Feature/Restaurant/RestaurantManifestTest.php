<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Modules\RestaurantManager\Domain\Contracts\SolutionManifest;
use App\Modules\RestaurantManager\Domain\Manifests\RestaurantManagerManifest;
use Tests\TestCase;

/**
 * RESTO-106 (#6163) — Manifest de la verticale RestaurantManager.
 *
 * Le manifest est exposé via le conteneur (singleton SolutionManifest) et
 * déclare l'identité, la maturité, les modules requis/optionnels, les
 * données sensibles et les permissions de la solution — prêt pour le
 * catalogue central PLAT-001.
 */
class RestaurantManifestTest extends TestCase
{
    public function test_manifest_is_registered_as_singleton(): void
    {
        $manifest = app(SolutionManifest::class);

        $this->assertInstanceOf(RestaurantManagerManifest::class, $manifest);
        $this->assertSame($manifest, app(SolutionManifest::class));
    }

    public function test_manifest_declares_identity_and_maturity(): void
    {
        $manifest = app(SolutionManifest::class);

        $this->assertSame('restaurantmanager', $manifest->code());
        $this->assertSame('RestaurantManager', $manifest->name());
        $this->assertSame('pilot', $manifest->maturity());
    }

    public function test_manifest_declares_modules_and_permissions(): void
    {
        $manifest = app(SolutionManifest::class);

        $this->assertSame(['rh', 'documents', 'notifications', 'crm'], $manifest->requiredModules());
        $this->assertSame(['accounting', 'marketing'], $manifest->optionalModules());
        $this->assertSame(['customer_pii', 'payments'], $manifest->sensitiveData());
        $this->assertContains('restaurant.manage', $manifest->permissions());
        $this->assertContains('restaurant.server', $manifest->permissions());
        $this->assertContains('restaurant.rider', $manifest->permissions());
        $this->assertContains('restaurant.reports', $manifest->permissions());
    }
}
