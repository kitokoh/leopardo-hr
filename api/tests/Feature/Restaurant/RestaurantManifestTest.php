<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;
use App\Core\Solutions\SolutionCatalogue;
use App\Modules\RestaurantManager\Domain\Manifests\RestaurantManagerManifest;
use Tests\TestCase;

/**
 * RESTO-106 (#6163) — Manifest de la verticale RestaurantManager.
 *
 * BOS-014 (#8201) : le manifest implémente le contrat CORE v2 et est
 * enregistré au `SolutionCatalogue` (clé `restaurantmanager`) — fin du
 * singleton de contrat local non conforme et non enregistré (anti-pattern
 * #7220-bis). Couvre : enregistrement catalogue, identité/maturité/industrie,
 * modules requis/optionnels, données sensibles et permissions en map
 * `code => libellé` (installées à l'activation, BOS-013).
 */
class RestaurantManifestTest extends TestCase
{
    public function test_manifest_is_registered_in_the_solution_catalogue(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->assertTrue($catalogue->has('restaurantmanager'));
        $this->assertInstanceOf(RestaurantManagerManifest::class, $catalogue->resolve('restaurantmanager'));
        $this->assertInstanceOf(SolutionManifest::class, $catalogue->resolve('restaurantmanager'));
    }

    public function test_manifest_declares_identity_maturity_and_industry(): void
    {
        $manifest = app(SolutionCatalogue::class)->resolve('restaurantmanager');

        $this->assertSame('restaurantmanager', $manifest->code());
        $this->assertSame('RestaurantManager', $manifest->name());
        $this->assertSame('pilot', $manifest->maturity());
        $this->assertSame(SolutionIndustry::Restaurant, $manifest->industry());
        $this->assertNotSame('', $manifest->description());
    }

    public function test_manifest_declares_modules_and_permissions(): void
    {
        $manifest = app(SolutionCatalogue::class)->resolve('restaurantmanager');

        $this->assertSame(['rh', 'documents', 'notifications', 'crm'], $manifest->requiredModules());
        $this->assertSame(['accounting', 'marketing'], $manifest->optionalModules());
        $this->assertSame(['customer_pii', 'payments'], $manifest->sensitiveData());

        $permissions = $manifest->permissions();
        foreach (['restaurant.manage', 'restaurant.manager', 'restaurant.server', 'restaurant.kitchen', 'restaurant.rider', 'restaurant.reports'] as $code) {
            $this->assertArrayHasKey($code, $permissions, "permission manquante : {$code}");
            $this->assertNotSame('', $permissions[$code], "libellé vide : {$code}");
        }
    }
}
