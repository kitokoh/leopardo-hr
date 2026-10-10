<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;
use App\Core\Solutions\SolutionCatalogue;
use App\Modules\Vtc\Domain\Manifests\VtcManifest;
use Tests\TestCase;

/**
 * VTC-01 (#8357, BC-34 VTC) — Manifest de la verticale VTC/taxi.
 *
 * Le manifest implémente le contrat CORE v2 (BOS-014) et est enregistré au
 * `SolutionCatalogue` (clé `vtc`). Couvre : enregistrement catalogue,
 * identité/maturité/industrie, modules requis/optionnels, données sensibles
 * et permissions en map `code => libellé` (pattern DeliveryManifestTest).
 */
class VtcManifestTest extends TestCase
{
    public function test_manifest_is_registered_in_the_solution_catalogue(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->assertTrue($catalogue->has('vtc'));
        $this->assertInstanceOf(VtcManifest::class, $catalogue->resolve('vtc'));
        $this->assertInstanceOf(SolutionManifest::class, $catalogue->resolve('vtc'));
    }

    public function test_manifest_declares_identity_maturity_and_industry(): void
    {
        $manifest = app(SolutionCatalogue::class)->resolve('vtc');

        $this->assertSame('vtc', $manifest->code());
        $this->assertSame('VTC & Taxi', $manifest->name());
        $this->assertSame('pilot', $manifest->maturity());
        $this->assertSame(SolutionIndustry::Mobility, $manifest->industry());
        $this->assertNotSame('', $manifest->description());
    }

    public function test_manifest_declares_modules_and_permissions(): void
    {
        $manifest = app(SolutionCatalogue::class)->resolve('vtc');

        // Seul `geo` est requis (dur) : le dispatch et les estimations
        // passent exclusivement par le core géospatial (spec §5).
        $this->assertSame(['geo'], $manifest->requiredModules());
        $this->assertSame(['notifications', 'fleet', 'billing'], $manifest->optionalModules());
        $this->assertSame(['customer_pii', 'location', 'payments'], $manifest->sensitiveData());

        $permissions = $manifest->permissions();
        foreach (['vtc.admin', 'vtc.dispatcher', 'vtc.driver', 'vtc.reports'] as $code) {
            $this->assertArrayHasKey($code, $permissions, "permission manquante : {$code}");
            $this->assertNotSame('', $permissions[$code]);
        }
    }
}
