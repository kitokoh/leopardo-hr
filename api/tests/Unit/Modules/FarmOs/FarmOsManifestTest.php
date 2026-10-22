<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use App\Core\Solutions\Enums\SolutionIndustry;
use App\Modules\FarmOs\Domain\Enums\FarmActivity;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Manifests\FarmOsManifest;
use PHPUnit\Framework\TestCase;

/**
 * Farm OS — Manifest de la verticale et registres fermés.
 */
final class FarmOsManifestTest extends TestCase
{
    public function test_manifest_identity_is_stable(): void
    {
        $manifest = new FarmOsManifest;

        $this->assertSame('farm_os', $manifest->code());
        $this->assertSame(FarmOsManifest::CODE, $manifest->code());
        $this->assertSame(SolutionIndustry::Agriculture, $manifest->industry());
        // Une verticale n'est jamais déclarée `production` avant un pilote réel.
        $this->assertSame('pilot', $manifest->maturity());
    }

    public function test_only_the_hr_foundation_is_a_hard_dependency(): void
    {
        $manifest = new FarmOsManifest;

        $this->assertSame(['rh'], $manifest->requiredModules());
        $this->assertContains('planning', $manifest->optionalModules());
        $this->assertContains('accounting', $manifest->optionalModules());
    }

    public function test_manifest_declares_farm_permissions(): void
    {
        $permissions = (new FarmOsManifest)->permissions();

        foreach (['farm.admin', 'farm.manager', 'farm.worker', 'farm.stock', 'farm.equipment', 'farm.livestock', 'farm.sales', 'farm.accounting', 'farm.regulated_inputs'] as $code) {
            $this->assertArrayHasKey($code, $permissions);
            $this->assertNotSame('', $permissions[$code]);
        }
    }

    public function test_industry_registry_contains_agriculture(): void
    {
        $this->assertSame(SolutionIndustry::Agriculture, SolutionIndustry::from('agriculture'));
    }

    public function test_activity_registry_covers_mandatory_farming_branches(): void
    {
        $values = FarmActivity::values();

        foreach (['cereal_crops', 'market_gardening', 'orchards', 'viticulture', 'greenhouse', 'cattle', 'sheep_goat', 'poultry', 'beekeeping', 'aquaculture', 'mixed_farming', 'cooperative', 'agri_services', 'agri_food'] as $expected) {
            $this->assertContains($expected, $values);
        }
    }

    public function test_capability_registry_exposes_the_thirteen_tools(): void
    {
        $this->assertCount(13, FarmCapability::values());
    }
}
