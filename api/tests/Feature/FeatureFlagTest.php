<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_rh_is_enabled_by_default(): void
    {
        $company = Company::query()->create($this->companyAttributes('Alpha', 'alpha'));

        $this->assertTrue(FeatureFlag::enabled('rh', $company));
    }

    public function test_non_rh_modules_are_disabled_by_default(): void
    {
        $company = Company::query()->create($this->companyAttributes('Beta', 'beta'));

        $this->assertFalse(FeatureFlag::enabled('finance', $company));
        $this->assertFalse(FeatureFlag::enabled('cameras', $company));
        $this->assertFalse(FeatureFlag::enabled('muhasebe', $company));
        $this->assertFalse(FeatureFlag::enabled('leo_ai', $company));
    }

    public function test_set_feature_persists_and_resolves(): void
    {
        $company = Company::query()->create($this->companyAttributes('Gamma', 'gamma'));

        $company->setFeature('finance', true);
        $company->save();

        $fresh = Company::query()->find($company->id);

        $this->assertTrue(FeatureFlag::enabled('finance', $fresh));
        $this->assertFalse(FeatureFlag::enabled('cameras', $fresh));
    }

    public function test_for_returns_full_map_for_known_modules(): void
    {
        $company = Company::query()->create($this->companyAttributes('Delta', 'delta'));
        $company->setFeature('cameras', true);
        $company->save();

        $map = FeatureFlag::for($company->fresh());

        $this->assertIsArray($map);
        $this->assertArrayHasKey('rh', $map);
        $this->assertArrayHasKey('finance', $map);
        $this->assertArrayHasKey('cameras', $map);
        $this->assertTrue($map['rh']);
        $this->assertTrue($map['cameras']);
        $this->assertFalse($map['finance']);
    }

    /**
     * Sans company, la résolution retourne le DÉFAUT VERSIONNÉ du registre.
     *
     * Le contrat est écrit dans le docblock de `FeatureFlagRegistry`
     * (« Résolution d'un flag, dans l'ordre, fail-closed ») :
     *   1. flag inconnu      -> false ;
     *   2. kill switch       -> false ;
     *   3. company présente  -> company.features ;
     *   4. sinon             -> défaut versionné du registre.
     *
     * Ce test affirmait « null company => tout désactivé », ce que le registre
     * ne fait délibérément pas : `rh` est le socle de l'application
     * (`default => true`, non killable). L'assertion contredisait donc le
     * contrat qu'elle prétendait vérifier — et échouait sur `main`
     * indépendamment de #7400.
     *
     * Le vrai garde-fou fail-closed est ailleurs, et il est désormais asserté
     * ici explicitement : un flag HORS registre reste désactivé.
     */
    public function test_null_company_resolves_from_versioned_registry_defaults(): void
    {
        $this->assertTrue(FeatureFlag::enabled('rh', null), 'rh est le socle (default true).');
        $this->assertFalse(FeatureFlag::enabled('finance', null), 'Les modules opt-in restent éteints.');

        // Fail-closed : un flag hors registre n'est jamais activé (règle 1).
        $this->assertFalse(FeatureFlag::enabled('flag_hors_registre', null));

        $map = FeatureFlag::for(null);
        $this->assertTrue($map['rh']);
        $this->assertFalse($map['finance']);
    }

    private function companyAttributes(string $name, string $slug): array
    {
        return [
            'name' => $name,
            'slug' => $slug,
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => "{$slug}@company.test",
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ];
    }
}

