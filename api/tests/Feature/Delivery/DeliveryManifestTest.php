<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;
use App\Core\Solutions\SolutionCatalogue;
use App\Modules\Delivery\Domain\Manifests\DeliveryManifest;
use Tests\TestCase;

/**
 * DELIVERY-101 (#6282) — Manifest du module Delivery (BC-26 DELIVERY).
 *
 * BOS-014 (#8201) : le manifest implémente le contrat CORE v2 et est
 * enregistré au `SolutionCatalogue` (clé `delivery`) — fin du singleton de
 * contrat local (anti-pattern #7220-bis). Couvre : enregistrement catalogue,
 * identité/maturité/industrie, modules requis/optionnels, données sensibles
 * et permissions en map `code => libellé`.
 */
class DeliveryManifestTest extends TestCase
{
    public function test_manifest_is_registered_in_the_solution_catalogue(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->assertTrue($catalogue->has('delivery'));
        $this->assertInstanceOf(DeliveryManifest::class, $catalogue->resolve('delivery'));
        $this->assertInstanceOf(SolutionManifest::class, $catalogue->resolve('delivery'));
    }

    public function test_manifest_declares_identity_maturity_and_industry(): void
    {
        $manifest = app(SolutionCatalogue::class)->resolve('delivery');

        $this->assertSame('delivery', $manifest->code());
        $this->assertSame('Delivery', $manifest->name());
        $this->assertSame('pilot', $manifest->maturity());
        $this->assertSame(SolutionIndustry::DeliveryLogistics, $manifest->industry());
        $this->assertNotSame('', $manifest->description());
    }

    public function test_manifest_declares_modules_and_permissions(): void
    {
        $manifest = app(SolutionCatalogue::class)->resolve('delivery');

        $this->assertSame(['rh', 'documents', 'notifications', 'crm', 'accounting'], $manifest->requiredModules());
        $this->assertSame(['fleet', 'marketing'], $manifest->optionalModules());
        $this->assertSame(['customer_pii', 'payments', 'location'], $manifest->sensitiveData());

        $permissions = $manifest->permissions();
        foreach (['delivery.admin', 'delivery.dispatcher', 'delivery.rider', 'delivery.manager', 'delivery.reports'] as $code) {
            $this->assertArrayHasKey($code, $permissions, "permission manquante : {$code}");
            $this->assertNotSame('', $permissions[$code]);
        }
    }
}
