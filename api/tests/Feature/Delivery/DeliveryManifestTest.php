<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Modules\Delivery\Domain\Contracts\SolutionManifest;
use App\Modules\Delivery\Domain\Manifests\DeliveryManifest;
use Tests\TestCase;

/**
 * DELIVERY-101 (#6282) — Manifest du module Delivery (BC-26 DELIVERY).
 *
 * Le manifest est exposé via le conteneur (singleton SolutionManifest) et
 * déclare l'identité, la maturité, les modules requis/optionnels, les
 * données sensibles et les permissions de la solution — prêt pour le
 * catalogue central PLAT-001.
 */
class DeliveryManifestTest extends TestCase
{
    public function test_manifest_is_registered_as_singleton(): void
    {
        $manifest = app(SolutionManifest::class);

        $this->assertInstanceOf(DeliveryManifest::class, $manifest);
        $this->assertSame($manifest, app(SolutionManifest::class));
    }

    public function test_manifest_declares_identity_and_maturity(): void
    {
        $manifest = app(SolutionManifest::class);

        $this->assertSame('delivery', $manifest->code());
        $this->assertSame('Delivery', $manifest->name());
        $this->assertSame('pilot', $manifest->maturity());
    }

    public function test_manifest_declares_modules_and_permissions(): void
    {
        $manifest = app(SolutionManifest::class);

        $this->assertSame(['rh', 'documents', 'notifications', 'crm', 'accounting'], $manifest->requiredModules());
        $this->assertSame(['fleet', 'marketing'], $manifest->optionalModules());
        $this->assertSame(['customer_pii', 'payments', 'location'], $manifest->sensitiveData());
        $this->assertContains('delivery.admin', $manifest->permissions());
        $this->assertContains('delivery.dispatcher', $manifest->permissions());
        $this->assertContains('delivery.rider', $manifest->permissions());
        $this->assertContains('delivery.manager', $manifest->permissions());
        $this->assertContains('delivery.reports', $manifest->permissions());
    }
}
