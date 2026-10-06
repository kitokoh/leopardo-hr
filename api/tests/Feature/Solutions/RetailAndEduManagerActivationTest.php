<?php

declare(strict_types=1);

namespace Tests\Feature\Solutions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;
use App\Core\Solutions\SolutionActivator;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\Onboarding\Domain\Services\SetupInterviewPlanner;
use App\Modules\Retail\Domain\Manifests\RetailManifest;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-016 (#8205) — Complétude d'activation : manifest Retail + seed minimal EduManager.
 */
class RetailAndEduManagerActivationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function createCompany(array $features = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'timezone' => 'Africa/Algiers',
            'features' => $features,
        ]);

        return $company;
    }

    private function createPrincipal(Company $company): Employee
    {
        return Employee::factory()->manager()->create(['company_id' => $company->id]);
    }

    public function test_retail_manifest_is_registered_and_conforms(): void
    {
        $catalogue = app(SolutionCatalogue::class);
        $manifest = $catalogue->resolve('retail');

        $this->assertInstanceOf(SolutionManifest::class, $manifest);
        $this->assertInstanceOf(RetailManifest::class, $manifest);
        $this->assertSame('retail', $manifest->code());
        $this->assertSame('Retail', $manifest->name());
        $this->assertSame('pilot', $manifest->maturity());
        $this->assertSame(SolutionIndustry::Retail, $manifest->industry());
        $this->assertSame(
            'Commerce de détail et point de vente : catalogue produits, gestion des stocks, caisse enregistreuse (POS), commandes en ligne et paiements multicanaux.',
            $manifest->description()
        );
        $this->assertSame(['rh', 'documents', 'notifications'], $manifest->requiredModules());
        $this->assertSame(['accounting', 'crm', 'delivery', 'marketing', 'payroll'], $manifest->optionalModules());
        $this->assertSame(['commandes et transactions', 'données clients et paiements'], $manifest->sensitiveData());

        $expectedPermissions = [
            'retail.pos' => 'Caisse POS : ouverture/fermeture session, encaissement, tickets',
            'retail.catalog' => 'Catalogue : gestion des produits, catégories, prix',
            'retail.stock' => 'Stocks : inventaire, réapprovisionnement, mouvements de stock',
            'retail.orders' => 'Commandes : suivi et traitement des commandes magasin et en ligne',
            'retail.settings' => 'Paramètres commerce : TVA, imprimante ticket, passerelles de paiement',
        ];
        $this->assertSame($expectedPermissions, $manifest->permissions());
    }

    public function test_retail_activation_installs_grants_for_principal(): void
    {
        $company = $this->createCompany(['documents' => true, 'notifications' => true]);
        $principal = $this->createPrincipal($company);

        $result = app(SolutionActivator::class)->activate($company, 'retail');

        $this->assertSame('activated', $result['status']);
        $this->assertTrue($company->fresh()->hasFeature('retail'));

        foreach (array_keys(app(SolutionCatalogue::class)->resolve('retail')->permissions()) as $perm) {
            $this->assertDatabaseHas('employee_module_grants', [
                'company_id' => $company->id,
                'employee_id' => $principal->id,
                'module_key' => $perm,
            ]);
        }
    }

    public function test_edumanager_activation_seeds_minimal_campus_and_academic_year(): void
    {
        $company = $this->createCompany(['documents' => true, 'notifications' => true]);
        $this->createPrincipal($company);

        $this->assertSame(0, EduCampus::forCompany($company)->count());
        $this->assertSame(0, EduAcademicYear::forCompany($company)->count());

        $result = app(SolutionActivator::class)->activate($company, 'edumanager');

        $this->assertSame('activated', $result['status']);

        // Campus vérifié
        $campus = EduCampus::forCompany($company)->first();
        $this->assertNotNull($campus);
        $this->assertSame('MAIN', $campus->code);
        $this->assertSame('Campus principal', $campus->name);
        $this->assertSame(EduCampus::STATUS_ACTIVE, $campus->status);
        $this->assertSame('Africa/Algiers', $campus->timezone);

        // Année académique vérifiée
        $year = EduAcademicYear::forCompany($company)->first();
        $this->assertNotNull($year);
        $this->assertSame('2026-2027', $year->name);
        $this->assertSame('2026-09-01', $year->start_date->format('Y-m-d'));
        $this->assertSame('2027-06-30', $year->end_date->format('Y-m-d'));
        $this->assertSame(EduAcademicYear::STATUS_ACTIVE, $year->status);
    }

    public function test_edumanager_activation_is_idempotent_and_does_not_duplicate_entities(): void
    {
        $company = $this->createCompany(['documents' => true, 'notifications' => true]);
        $this->createPrincipal($company);

        $activator = app(SolutionActivator::class);
        $activator->activate($company, 'edumanager');

        $this->assertSame(1, EduCampus::forCompany($company)->count());
        $this->assertSame(1, EduAcademicYear::forCompany($company)->count());

        // Ré-activation manuelle / trigger SolutionActivated
        $activator->activate($company, 'edumanager');

        $this->assertSame(1, EduCampus::forCompany($company)->count());
        $this->assertSame(1, EduAcademicYear::forCompany($company)->count());
    }

    public function test_setup_interview_planner_maps_commerce_to_retail(): void
    {
        $planner = app(SetupInterviewPlanner::class);
        $plan = $planner->plan([
            'sector' => 'commerce',
            'team_size' => '2-10',
            'main_priority' => 'payroll',
        ]);

        $this->assertContains('retail', $plan['suggested_solutions']);
    }
}
