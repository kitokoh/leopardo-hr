<?php

namespace Tests\Feature\Restaurant;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Tenant\Domain\Models\Company;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7976 — la verticale Restaurant doit être ACTIVABLE en standard.
 *
 * Constat : `restaurantmanager` est le flag posé par
 * `ActivateRestaurantManagerAction` et le gate des routes du middleware
 * `module.restaurantmanager` (210 routes), mais il était absent des deux
 * registres canoniques (`config/feature-flags.php` et
 * `Company::KNOWN_MODULES`). Or `PlatformCompanyFeatureController::update()`
 * reconstruit la carte de features à partir de KNOWN_MODULES et
 * `FeatureFlag::for()` ignore tout flag hors registre : après une activation
 * standard, toute la verticale répondait 403 (même leçon que #7220/#7235).
 *
 * Contrat : `restaurantmanager` est enregistré dans les deux registres,
 * exposé par FeatureFlag::for(), actif uniquement après activation explicite
 * (fail-closed par défaut).
 */
class RestaurantManagerModuleRegistryTest extends TestCase
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

    public function test_restaurantmanager_is_registered_in_feature_flags_registry(): void
    {
        $flags = config('feature-flags.flags');

        $this->assertIsArray($flags);
        $this->assertArrayHasKey(
            'restaurantmanager',
            $flags,
            'restaurantmanager doit être enregistré dans config/feature-flags.php, sinon FeatureFlag::for() l\'ignore.',
        );
        $this->assertFalse($flags['restaurantmanager']['default'], 'Fail-closed : défaut false.');
        $this->assertSame('solution', $flags['restaurantmanager']['scope']);
    }

    public function test_restaurantmanager_is_registered_as_known_module(): void
    {
        $this->assertContains(
            'restaurantmanager',
            Company::KNOWN_MODULES,
            'restaurantmanager doit être dans Company::KNOWN_MODULES pour être activable par la plateforme.',
        );
    }

    public function test_restaurantmanager_is_fail_closed_by_default_and_exposed_in_flag_map(): void
    {
        $company = Company::query()->create($this->companyAttributes('RestoCo', 'restoco'));

        $this->assertFalse($company->hasFeature('restaurantmanager'));

        $map = FeatureFlag::for($company);

        // Exposé dans la carte résolue (sinon l'admin ne peut pas le toggler).
        $this->assertArrayHasKey('restaurantmanager', $map);
        $this->assertFalse($map['restaurantmanager']);
    }

    public function test_restaurantmanager_becomes_true_after_activation(): void
    {
        $company = Company::query()->create($this->companyAttributes('RestoCo2', 'restoco2'));

        $company->setFeature('restaurantmanager', true);
        $company->save();

        $fresh = Company::query()->findOrFail($company->id);

        $this->assertTrue($fresh->hasFeature('restaurantmanager'));
        $this->assertTrue(FeatureFlag::for($fresh)['restaurantmanager']);
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
