<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7640 (TRAVEL-ONBOARDING) — vertical « Agence de voyage » dans l'entretien
 * de préparation : un inscrit qui se déclare agence obtient le module
 * `travelagency` actif SANS intervention plateforme (activation via
 * `CompleteSetupInterview` → `SolutionActivator`, chemin canonique #6693)
 * et une checklist d'onboarding orientée agence (créer son réseau, son
 * premier voyage, sa première vente — `SeedDefaultSteps`).
 */
class SetupInterviewTravelVerticalTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
        ]);

        return $company;
    }

    private function actingAsPrincipal(Company $company): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);
    }

    /**
     * @return array{metadata: array<string, mixed>, features: array<string, mixed>}
     */
    private function persistedCompany(Company $company): array
    {
        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
        $row = DB::table($table)->where('id', $company->id)->first();

        return [
            'metadata' => json_decode((string) ($row->metadata ?? '{}'), true) ?? [],
            'features' => json_decode((string) ($row->features ?? '{}'), true) ?? [],
        ];
    }

    public function test_declared_travel_agency_gets_module_active_without_platform_intervention(): void
    {
        $company = $this->company();
        $this->actingAsPrincipal($company);

        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => [
                'company_type' => 'team',
                'team_size' => '1-10',
                'sector' => 'travel',
                'premises' => 'multiple',
            ],
        ])->assertOk();

        $response = $this->postJson('/api/v1/setup-interview/complete');
        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertContains(
            'travelagency',
            $response->json('data.activated.solutions'),
            'Le secteur « travel » doit activer la verticale travelagency (allowlist SetupInterviewPlanner).'
        );

        // Le feature flag tenant est persisté : l'entrée « Voyages » de la
        // navigation web (client-features, featureKeys travelagency) apparaît
        // sans aucune intervention plateforme.
        $persisted = $this->persistedCompany($company);
        $this->assertTrue(
            (bool) ($persisted['features']['travelagency'] ?? false),
            'Le flag companies.features.travelagency doit être posé à la clôture de l\'entretien.'
        );
        $this->assertSame('completed', $persisted['metadata']['setup_interview']['status'] ?? null);
    }

    public function test_travel_company_gets_agency_oriented_checklist(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['travelagency' => true],
            'metadata' => [
                'setup_interview' => [
                    'status' => 'completed',
                    'answers' => ['company_type' => 'team', 'sector' => 'travel', 'premises' => 'multiple'],
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        (new SeedDefaultSteps)->execute((string) $company->id);

        $keys = OnboardingStep::where('company_id', $company->id)
            ->orderBy('order')
            ->pluck('step_key')
            ->all();

        // Checklist orientée agence : réseau → premier voyage → première vente.
        $this->assertContains('travel_setup_network', $keys);
        $this->assertContains('travel_first_trip', $keys);
        $this->assertContains('travel_first_sale', $keys);
        $this->assertLessThan(
            array_search('travel_first_trip', $keys, true),
            array_search('travel_setup_network', $keys, true),
            'Le réseau se crée avant le premier voyage.'
        );
        $this->assertLessThan(
            array_search('travel_first_sale', $keys, true),
            array_search('travel_first_trip', $keys, true),
            'Le premier voyage se programme avant la première vente.'
        );
    }

    public function test_non_travel_company_never_sees_travel_steps(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => [
                'modules' => ['employees' => true],
                'setup_interview' => [
                    'status' => 'completed',
                    'answers' => ['company_type' => 'team', 'sector' => 'commerce', 'premises' => 'single'],
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        (new SeedDefaultSteps)->execute((string) $company->id);

        $keys = OnboardingStep::where('company_id', $company->id)->pluck('step_key')->all();

        $this->assertNotContains('travel_setup_network', $keys);
        $this->assertNotContains('travel_first_trip', $keys);
        $this->assertNotContains('travel_first_sale', $keys);
    }
}
