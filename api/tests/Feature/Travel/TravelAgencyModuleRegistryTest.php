<?php

namespace Tests\Feature\Travel;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Tenant\Domain\Models\Company;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7220 (audit 2026-09-10) — la verticale Agence de voyage doit être
 * ACTIVABLE depuis l'admin plateforme.
 *
 * Constat : `travelagency` est le code du `TravelAgencyManifest` et le flag
 * posé par `ActivateTravelAgencyAction`, mais il était absent de
 * `Company::KNOWN_MODULES`. Or `PlatformCompanyFeatureController::update()`
 * reconstruit la carte de features à partir de ce registre : la verticale
 * Travel ne pouvait donc jamais être activée ni exposée (un client agence de
 * voyage restait sans module métier — défaut remonté par le propriétaire).
 *
 * Contrat : `travelagency` est enregistré, exposé par FeatureFlag::for(),
 * actif uniquement après activation explicite (fail-closed par défaut).
 */
class TravelAgencyModuleRegistryTest extends TestCase
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

    public function test_travelagency_is_registered_as_known_module(): void
    {
        $this->assertContains(
            'travelagency',
            Company::KNOWN_MODULES,
            'travelagency doit être dans Company::KNOWN_MODULES pour être activable par la plateforme.',
        );
    }

    public function test_travelagency_is_fail_closed_by_default_and_exposed_in_flag_map(): void
    {
        $company = Company::query()->create($this->companyAttributes('TravelCo', 'travelco'));

        $this->assertFalse($company->hasFeature('travelagency'));

        $map = FeatureFlag::for($company);

        // Exposé dans la carte résolue (sinon l'admin ne peut pas le toggler).
        $this->assertArrayHasKey('travelagency', $map);
        $this->assertFalse($map['travelagency']);
    }

    public function test_travelagency_becomes_true_after_activation(): void
    {
        $company = Company::query()->create($this->companyAttributes('TravelCo2', 'travelco2'));

        $company->setFeature('travelagency', true);
        $company->save();

        $fresh = Company::query()->findOrFail($company->id);

        $this->assertTrue($fresh->hasFeature('travelagency'));
        $this->assertTrue(FeatureFlag::for($fresh)['travelagency']);
    }

    /** @return array<string, mixed> */
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
