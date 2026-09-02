<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Solutions\Exceptions\SolutionMissingDependencyException;
use App\Core\Solutions\Exceptions\SolutionNotFoundException;
use App\Core\Solutions\SolutionActivator;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Solution\EduManagerManifest;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5817 (EDU-001) — manifest de solution EduManager, catalogue
 * allowlist et activation par tenant (feature flag).
 *
 * Couvre : manifest enregistré dans le catalogue, code inconnu refusé
 * (fail-closed), activation idempotente, dépendances manquantes refusées,
 * flag `edumanager` exposé par FeatureFlag::for() (défaut false), audit,
 * et non-régression du CRM commercial plateforme.
 */
class EduSolutionManifestTest extends TestCase
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

    public function test_edumanager_manifest_is_registered_in_catalogue(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->assertTrue($catalogue->has('edumanager'));
        $this->assertContains('edumanager', $catalogue->codes());

        $manifest = $catalogue->resolve('edumanager');
        $this->assertInstanceOf(EduManagerManifest::class, $manifest);
        $this->assertSame('edumanager', $manifest->code());
        $this->assertSame('pilot', $manifest->maturity());
        $this->assertContains('rh', $manifest->requiredModules());
        $this->assertContains('documents', $manifest->requiredModules());
        $this->assertContains('notifications', $manifest->requiredModules());
        $this->assertContains('crm', $manifest->optionalModules());
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

        $first = $activator->activate($company, 'edumanager');
        $this->assertSame('activated', $first['status']);
        $company->refresh();
        $this->assertTrue($company->hasFeature('edumanager'));

        $second = $activator->activate($company, 'edumanager');
        $this->assertSame('already_active', $second['status']);
        $company->refresh();
        $this->assertTrue($company->hasFeature('edumanager'));
    }

    public function test_activation_refuses_missing_dependencies(): void
    {
        // Tenant sans le module documents (requis par le manifest EduManager).
        $company = $this->company(['rh' => true]);

        $this->expectException(SolutionMissingDependencyException::class);

        try {
            app(SolutionActivator::class)->activate($company, 'edumanager');
        } catch (SolutionMissingDependencyException $exception) {
            $this->assertContains('documents', $exception->missing);
            $company->refresh();
            $this->assertFalse($company->hasFeature('edumanager'));

            throw $exception;
        }
    }

    public function test_activation_sets_feature_flag_and_audits(): void
    {
        $company = $this->company(['rh' => true, 'documents' => true, 'notifications' => true]);

        app(SolutionActivator::class)->activate($company, 'edumanager');

        $company->refresh();
        $this->assertTrue($company->hasFeature('edumanager'));

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'solution.activated',
        ]);
    }

    public function test_edumanager_flag_is_exposed_and_fail_closed_by_default(): void
    {
        // Tenant SANS activation → flag false (fail-closed).
        $company = $this->company();

        $this->assertFalse($company->hasFeature('edumanager'));

        $flags = FeatureFlag::for($company);
        $this->assertFalse($flags['edumanager'] ?? true);
    }

    public function test_activation_never_touches_platform_crm(): void
    {
        $company = $this->company(['rh' => true, 'documents' => true, 'notifications' => true]);
        $before = DB::table('marketing_leads')->count();

        app(SolutionActivator::class)->activate($company, 'edumanager');

        $this->assertSame($before, DB::table('marketing_leads')->count());
    }
}
