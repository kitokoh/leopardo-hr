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
 * RESTO-ONBOARDING — vertical « Restaurant » dans l'entretien de préparation.
 *
 * Un inscrit qui se déclare « restaurant » obtient le module
 * `restaurantmanager` actif SANS intervention plateforme (activation via
 * `CompleteSetupInterview` → `SolutionActivator`, chemin canonique #6693) et
 * une checklist d'onboarding ORIENTÉE RESTAURANT (créer son établissement,
 * composer son menu, configurer sa caisse, encaisser sa première commande —
 * `SeedDefaultSteps`).
 *
 * Avant ce correctif, un restaurateur recevait la checklist RH générique
 * (« Créer le premier département », « Configurer la paie », « Installer un
 * kiosque ») : aucun rapport avec son métier.
 *
 * Miroir du test de la verticale sœur `SetupInterviewTravelVerticalTest`.
 */
class SetupInterviewRestaurantVerticalTest extends TestCase
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

    public function test_declared_restaurant_gets_module_active_without_platform_intervention(): void
    {
        $company = $this->company();
        $this->actingAsPrincipal($company);

        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => [
                'company_type' => 'team',
                'team_size' => '1-10',
                'sector' => 'restaurant',
                'premises' => 'single',
            ],
        ])->assertOk();

        $response = $this->postJson('/api/v1/setup-interview/complete');
        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // Le secteur « restaurant » active le code DESCRIPTEUR `restaurant`
        // (allowlist SetupInterviewPlanner) ; la cascade `SolutionActivated`
        // pose ensuite le flag opérationnel `restaurantmanager`.
        $this->assertContains(
            'restaurant',
            $response->json('data.activated.solutions'),
            'Le secteur « restaurant » doit activer la verticale restaurant (allowlist SetupInterviewPlanner).'
        );

        $persisted = $this->persistedCompany($company);
        $this->assertTrue(
            (bool) ($persisted['features']['restaurantmanager'] ?? false),
            'Le flag opérationnel companies.features.restaurantmanager doit être posé par la cascade d\'activation.'
        );
        $this->assertSame('completed', $persisted['metadata']['setup_interview']['status'] ?? null);
    }

    public function test_restaurant_company_gets_restaurant_oriented_checklist(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['restaurantmanager' => true],
            'metadata' => [
                'setup_interview' => [
                    'status' => 'completed',
                    'answers' => ['company_type' => 'team', 'sector' => 'restaurant', 'premises' => 'single'],
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        (new SeedDefaultSteps)->execute((string) $company->id);

        $keys = OnboardingStep::where('company_id', $company->id)
            ->orderBy('order')
            ->pluck('step_key')
            ->all();

        // Checklist orientée restaurant : établissement → menu → caisse → 1re commande.
        $this->assertContains('restaurant_setup_establishment', $keys);
        $this->assertContains('restaurant_setup_menu', $keys);
        $this->assertContains('restaurant_setup_pos', $keys);
        $this->assertContains('restaurant_first_order', $keys);

        $this->assertLessThan(
            array_search('restaurant_setup_menu', $keys, true),
            array_search('restaurant_setup_establishment', $keys, true),
            'L\'établissement se crée avant le menu.'
        );
        $this->assertLessThan(
            array_search('restaurant_setup_pos', $keys, true),
            array_search('restaurant_setup_menu', $keys, true),
            'Le menu se compose avant la configuration de la caisse.'
        );
        $this->assertLessThan(
            array_search('restaurant_first_order', $keys, true),
            array_search('restaurant_setup_pos', $keys, true),
            'La caisse se configure avant la première commande.'
        );
    }

    public function test_restaurant_checklist_also_applies_for_descriptor_code_only(): void
    {
        // Un tenant activé par le seul code DESCRIPTEUR `restaurant` (avant que
        // la cascade n'ait posé `restaurantmanager`) doit lui aussi recevoir la
        // checklist métier — les deux codes mènent au même espace.
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['restaurant' => true],
            'metadata' => [
                'setup_interview' => [
                    'status' => 'completed',
                    'answers' => ['company_type' => 'team', 'sector' => 'restaurant', 'premises' => 'single'],
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        (new SeedDefaultSteps)->execute((string) $company->id);

        $keys = OnboardingStep::where('company_id', $company->id)->pluck('step_key')->all();

        $this->assertContains('restaurant_setup_establishment', $keys);
        $this->assertContains('restaurant_first_order', $keys);
    }

    public function test_non_restaurant_company_never_sees_restaurant_steps(): void
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

        $this->assertNotContains('restaurant_setup_establishment', $keys);
        $this->assertNotContains('restaurant_setup_menu', $keys);
        $this->assertNotContains('restaurant_setup_pos', $keys);
        $this->assertNotContains('restaurant_first_order', $keys);
    }

    public function test_restaurant_steps_are_localized_in_all_locales(): void
    {
        // Garde i18n (PA2-I18N-007 / #5432) : les titres viennent du catalogue,
        // jamais d'une chaîne française en dur dans l'Action.
        foreach (['fr', 'en', 'ar', 'tr'] as $locale) {
            app()->setLocale($locale);

            foreach ([
                'onboarding.step_restaurant_setup_establishment',
                'onboarding.step_restaurant_setup_menu',
                'onboarding.step_restaurant_setup_pos',
                'onboarding.step_restaurant_first_order',
            ] as $key) {
                $this->assertNotSame(
                    $key,
                    __($key),
                    "La clé {$key} doit être traduite en {$locale}."
                );
            }
        }
    }
}
