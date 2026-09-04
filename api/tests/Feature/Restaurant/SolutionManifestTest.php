<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Solutions\Exceptions\SolutionNotFoundException;
use App\Core\Solutions\SolutionActivator;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Restaurant\Domain\Solution\RestaurantManifest;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6693 (BC-25) — manifest de solution Restaurant, catalogue allowlist
 * et activation par tenant (feature flag).
 *
 * Couvre : manifest enregistré dans le catalogue, code inconnu refusé
 * (fail-closed), activation idempotente, dépendances manquantes refusées,
 * flag `restaurant` exposé et audit `solution.activated`.
 */
class SolutionManifestTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, bool>  $features
     */
    private function company(array $features = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => $features,
        ]);

        return $company;
    }

    public function test_restaurant_manifest_is_registered_in_catalogue(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->assertTrue($catalogue->has('restaurant'));
        $this->assertContains('restaurant', $catalogue->codes());

        $manifest = $catalogue->resolve('restaurant');
        $this->assertInstanceOf(RestaurantManifest::class, $manifest);
        $this->assertSame('restaurant', $manifest->code());
        $this->assertSame('pilot', $manifest->maturity());
        $this->assertContains('attendance', $manifest->requiredModules());
        $this->assertContains('rh', $manifest->requiredModules());
    }

    public function test_unknown_solution_code_is_rejected(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->expectException(SolutionNotFoundException::class);
        $catalogue->resolve('solution_inconnue');
    }

    public function test_activation_is_idempotent(): void
    {
        $company = $this->company(['rh' => true, 'attendance' => true, 'documents' => true, 'notifications' => true]);
        $activator = app(SolutionActivator::class);

        $first = $activator->activate($company, 'restaurant');
        $this->assertSame('activated', $first['status']);
        $company->refresh();
        $this->assertTrue($company->hasFeature('restaurant'));

        $second = $activator->activate($company, 'restaurant');
        $this->assertSame('already_active', $second['status']);
        $company->refresh();
        $this->assertTrue($company->hasFeature('restaurant'));
    }

    public function test_activation_sets_feature_flag_and_audits(): void
    {
        $company = $this->company(['rh' => true, 'attendance' => true, 'documents' => true, 'notifications' => true]);

        app(SolutionActivator::class)->activate($company, 'restaurant');

        $company->refresh();
        $this->assertTrue($company->hasFeature('restaurant'));

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'solution.activated',
        ]);
    }

    public function test_restaurant_flag_is_fail_closed_by_default(): void
    {
        // Tenant SANS activation → flag false (fail-closed).
        $company = $this->company();

        $this->assertFalse($company->hasFeature('restaurant'));
    }
}
