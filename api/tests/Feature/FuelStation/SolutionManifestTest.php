<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Solutions\Exceptions\SolutionMissingDependencyException;
use App\Core\Solutions\Exceptions\SolutionNotFoundException;
use App\Core\Solutions\SolutionActivator;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Solution\FuelStationManifest;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5795 (FUEL-001) — manifest de solution FuelStation, catalogue
 * allowlist et activation par tenant (feature flag).
 *
 * Couvre : manifest enregistré dans le catalogue, code inconnu refusé
 * (fail-closed), activation idempotente, dépendances manquantes refusées,
 * flag `fuel_station` exposé par FeatureFlag::for() (défaut false), audit.
 */
class SolutionManifestTest extends TestCase
{
    use RefreshTenantDatabase;

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

    public function test_fuel_station_manifest_is_registered_in_catalogue(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->assertTrue($catalogue->has('fuel_station'));
        $this->assertContains('fuel_station', $catalogue->codes());

        $manifest = $catalogue->resolve('fuel_station');
        $this->assertInstanceOf(FuelStationManifest::class, $manifest);
        $this->assertSame('fuel_station', $manifest->code());
        $this->assertSame('pilot', $manifest->maturity());
        $this->assertContains('attendance', $manifest->requiredModules());
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

        $first = $activator->activate($company, 'fuel_station');
        $this->assertSame('activated', $first['status']);
        $this->assertTrue($company->fresh()->hasFeature('fuel_station'));

        $second = $activator->activate($company, 'fuel_station');
        $this->assertSame('already_active', $second['status']);
        $this->assertTrue($company->fresh()->hasFeature('fuel_station'));
    }

    public function test_activation_refuses_missing_dependencies(): void
    {
        // Tenant sans le module attendance (requis par le manifest).
        $company = $this->company(['rh' => true]);

        $this->expectException(SolutionMissingDependencyException::class);

        try {
            app(SolutionActivator::class)->activate($company, 'fuel_station');
        } catch (SolutionMissingDependencyException $exception) {
            $this->assertContains('attendance', $exception->missing);
            $this->assertFalse($company->fresh()->hasFeature('fuel_station'));

            throw $exception;
        }
    }

    public function test_activation_sets_feature_flag_and_audits(): void
    {
        $company = $this->company(['rh' => true, 'attendance' => true, 'documents' => true, 'notifications' => true]);

        app(SolutionActivator::class)->activate($company, 'fuel_station');

        $this->assertTrue($company->fresh()->hasFeature('fuel_station'));

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'solution.activated',
        ]);
    }

    public function test_fuel_station_flag_is_exposed_and_fail_closed_by_default(): void
    {
        // Tenant SANS activation → flag false (fail-closed).
        $company = $this->company();

        $this->assertFalse($company->hasFeature('fuel_station'));

        // Le flag apparaît dans la carte résolue (FeatureFlag::for).
        $flags = \App\Core\Feature\Infrastructure\Services\FeatureFlag::for($company);
        $this->assertArrayHasKey('fuel_station', $flags);
        $this->assertFalse($flags['fuel_station']);
    }

    public function test_activation_never_touches_platform_crm(): void
    {
        $company = $this->company(['rh' => true, 'attendance' => true, 'documents' => true, 'notifications' => true]);
        $before = DB::table('marketing_leads')->count();

        app(SolutionActivator::class)->activate($company, 'fuel_station');

        $this->assertSame($before, DB::table('marketing_leads')->count());
    }
}
