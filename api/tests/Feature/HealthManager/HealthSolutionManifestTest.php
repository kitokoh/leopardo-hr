<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Solutions\Exceptions\SolutionMissingDependencyException;
use App\Core\Solutions\Exceptions\SolutionNotFoundException;
use App\Core\Solutions\SolutionActivator;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Solution\HealthManagerManifest;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * HC-001 (#7785) — manifest de solution HealthManager, catalogue allowlist
 * et activation par tenant (feature flag `healthmanager`).
 *
 * Couvre : manifest enregistré dans le catalogue, code inconnu refusé
 * (fail-closed), activation idempotente, dépendances manquantes refusées,
 * flag `healthmanager` fail-closed par défaut (scope solution, défaut
 * false), audit `solution.activated`, permissions du manifest.
 */
class HealthSolutionManifestTest extends TestCase
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

    public function test_healthmanager_manifest_is_registered_in_catalogue(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->assertTrue($catalogue->has('healthmanager'));
        $this->assertContains('healthmanager', $catalogue->codes());

        $manifest = $catalogue->resolve('healthmanager');
        $this->assertInstanceOf(HealthManagerManifest::class, $manifest);
        $this->assertSame('healthmanager', $manifest->code());
        $this->assertSame('pilot', $manifest->maturity());
        $this->assertContains('rh', $manifest->requiredModules());
        $this->assertContains('documents', $manifest->requiredModules());
        $this->assertContains('notifications', $manifest->requiredModules());
    }

    public function test_manifest_declares_health_permissions_and_sensitive_data(): void
    {
        $manifest = new HealthManagerManifest;

        $permissions = $manifest->permissions();
        $this->assertArrayHasKey('health.admin', $permissions);
        $this->assertArrayHasKey('health.practitioner', $permissions);
        $this->assertArrayHasKey('health.reception', $permissions);
        $this->assertArrayHasKey('health.billing', $permissions);

        // Données de santé déclarées (RGPD art. 9) — jamais une liste vide.
        $this->assertNotSame([], $manifest->sensitiveData());
    }

    public function test_unknown_solution_code_is_rejected(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->expectException(SolutionNotFoundException::class);
        $catalogue->resolve('solution_inconnue');
    }

    public function test_activation_is_idempotent(): void
    {
        $company = $this->company(['rh' => true, 'documents' => true, 'notifications' => true]);
        $activator = app(SolutionActivator::class);

        $first = $activator->activate($company, 'healthmanager');
        $this->assertSame('activated', $first['status']);
        $company->refresh();
        $this->assertTrue($company->hasFeature('healthmanager'));

        $second = $activator->activate($company, 'healthmanager');
        $this->assertSame('already_active', $second['status']);
        $company->refresh();
        $this->assertTrue($company->hasFeature('healthmanager'));
    }

    public function test_activation_refuses_missing_dependencies(): void
    {
        // Tenant sans les modules documents/notifications (requis par le manifest).
        $company = $this->company(['rh' => true]);

        $this->expectException(SolutionMissingDependencyException::class);

        try {
            app(SolutionActivator::class)->activate($company, 'healthmanager');
        } catch (SolutionMissingDependencyException $exception) {
            $this->assertContains('documents', $exception->missing);
            $this->assertContains('notifications', $exception->missing);
            $company->refresh();
            $this->assertFalse($company->hasFeature('healthmanager'));

            throw $exception;
        }
    }

    public function test_activation_sets_feature_flag_and_audits(): void
    {
        $company = $this->company(['rh' => true, 'documents' => true, 'notifications' => true]);

        app(SolutionActivator::class)->activate($company, 'healthmanager');

        $company->refresh();
        $this->assertTrue($company->hasFeature('healthmanager'));

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'solution.activated',
        ]);
    }

    public function test_healthmanager_flag_is_fail_closed_by_default(): void
    {
        // Tenant SANS activation → flag false (fail-closed).
        $company = $this->company();

        $this->assertFalse($company->hasFeature('healthmanager'));
        $this->assertFalse(FeatureFlag::enabled('healthmanager', $company));

        // `healthmanager` est dans Company::KNOWN_MODULES (leçon #7220/#7235) :
        // la carte résolue expose la clé, à false par défaut.
        $flags = FeatureFlag::for($company);
        $this->assertFalse($flags['healthmanager'] ?? false);
    }
}
