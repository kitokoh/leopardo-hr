<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8451 — checklists de mise en service par profil métier (généralisation).
 *
 * Après la verticale restaurant (RESTO-ONBOARDING), TOUT profil doit
 * terminer l'onboarding avec une checklist orientée sur SON métier :
 *  - station-service : station → équipe/rotations → première vente ;
 *  - commerce de détail : catalogue → caisse → première vente ;
 *  - scolarité : campus/année → classes → premiers élèves ;
 *  - et, pour les verticales à écrans métier, l'étape « installer ses
 *    applications » qui mène au hub « Mon Logiciel » (#8447).
 *
 * Miroir de `SetupInterviewRestaurantVerticalTest` (mêmes conventions :
 * société seedée avec features + entretien complété, seed déclaratif).
 */
class SetupInterviewVerticalChecklistsTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $features
     * @param  array<string, mixed>  $answers
     */
    private function companyWithInterview(array $features, array $answers): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => $features,
            'metadata' => [
                'setup_interview' => [
                    'status' => 'completed',
                    'answers' => $answers,
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        return $company;
    }

    /** @return list<string> */
    private function seededKeys(Company $company): array
    {
        (new SeedDefaultSteps)->execute((string) $company->id);

        return OnboardingStep::where('company_id', $company->id)
            ->orderBy('order')
            ->pluck('step_key')
            ->all();
    }

    public function test_fuel_station_company_gets_fuel_oriented_checklist(): void
    {
        $company = $this->companyWithInterview(
            ['fuel_station' => true],
            ['company_type' => 'team', 'sector' => 'fuel_station', 'premises' => 'multiple'],
        );

        $keys = $this->seededKeys($company);

        $this->assertContains('fuel_setup_station', $keys);
        $this->assertContains('fuel_setup_team', $keys);
        $this->assertContains('fuel_first_rotation', $keys);
        $this->assertContains('fuel_first_sale', $keys);

        $this->assertLessThan(
            array_search('fuel_setup_team', $keys, true),
            array_search('fuel_setup_station', $keys, true),
            'La station se crée avant son équipe.'
        );
        $this->assertLessThan(
            array_search('fuel_first_rotation', $keys, true),
            array_search('fuel_setup_team', $keys, true),
            'L\'équipe existe avant sa première rotation.'
        );
    }

    public function test_fuel_station_checklist_also_applies_for_operational_flag_only(): void
    {
        // Détection par le flag opérationnel `fuel` (featureKeys du catalogue
        // front) comme par le code déclaré `fuel_station`.
        $company = $this->companyWithInterview(
            ['fuel' => true],
            ['company_type' => 'team', 'sector' => 'fuel_station', 'premises' => 'single'],
        );

        $keys = $this->seededKeys($company);

        $this->assertContains('fuel_setup_station', $keys);
        $this->assertContains('fuel_first_sale', $keys);
    }

    public function test_fuel_solo_company_never_gets_team_steps(): void
    {
        // Un exploitant seul (profil `solo`) n'a ni équipe à constituer ni
        // rotation à planifier — jamais d'étape sans rapport avec son profil.
        $company = $this->companyWithInterview(
            ['fuel_station' => true],
            ['company_type' => 'solo', 'sector' => 'fuel_station', 'premises' => 'single'],
        );

        $keys = $this->seededKeys($company);

        $this->assertContains('fuel_setup_station', $keys);
        $this->assertNotContains('fuel_setup_team', $keys);
        $this->assertNotContains('fuel_first_rotation', $keys);
    }

    public function test_retail_company_gets_retail_oriented_checklist(): void
    {
        $company = $this->companyWithInterview(
            ['retail' => true],
            ['company_type' => 'team', 'sector' => 'commerce', 'premises' => 'single'],
        );

        $keys = $this->seededKeys($company);

        $this->assertContains('retail_setup_catalog', $keys);
        $this->assertContains('retail_setup_pos', $keys);
        $this->assertContains('retail_first_sale', $keys);

        $this->assertLessThan(
            array_search('retail_setup_pos', $keys, true),
            array_search('retail_setup_catalog', $keys, true),
            'Le catalogue existe avant la configuration de la caisse.'
        );
    }

    public function test_edumanager_company_gets_education_oriented_checklist(): void
    {
        $company = $this->companyWithInterview(
            ['edumanager' => true],
            ['company_type' => 'team', 'sector' => 'education', 'premises' => 'single'],
        );

        $keys = $this->seededKeys($company);

        $this->assertContains('edu_setup_campus', $keys);
        $this->assertContains('edu_setup_classes', $keys);
        $this->assertContains('edu_first_students', $keys);

        $this->assertLessThan(
            array_search('edu_setup_classes', $keys, true),
            array_search('edu_setup_campus', $keys, true),
            'Le campus et l\'année existent avant les classes.'
        );
        $this->assertLessThan(
            array_search('edu_first_students', $keys, true),
            array_search('edu_setup_classes', $keys, true),
            'Les classes existent avant l\'inscription des élèves.'
        );
    }

    public function test_verticals_with_business_screens_get_install_apps_step(): void
    {
        foreach (['restaurantmanager', 'fuel_station', 'retail'] as $feature) {
            $company = $this->companyWithInterview(
                [$feature => true],
                ['company_type' => 'team', 'sector' => 'other', 'premises' => 'single'],
            );

            $keys = $this->seededKeys($company);

            $this->assertContains(
                'install_apps',
                $keys,
                "La verticale {$feature} doit proposer l'étape « installer ses applications » (hub Mon Logiciel)."
            );

            // L'étape d'installation ne bloque jamais le go-live.
            $step = OnboardingStep::where('company_id', $company->id)->where('step_key', 'install_apps')->first();
            $this->assertNotNull($step);
            $this->assertFalse((bool) $step->required);
        }
    }

    public function test_company_without_vertical_never_sees_vertical_or_install_steps(): void
    {
        $company = $this->companyWithInterview(
            ['employees' => true],
            ['company_type' => 'team', 'sector' => 'services', 'premises' => 'none'],
        );

        $keys = $this->seededKeys($company);

        foreach (['fuel_setup_station', 'retail_setup_catalog', 'edu_setup_campus', 'install_apps'] as $verticalKey) {
            $this->assertNotContains($verticalKey, $keys);
        }
    }
}
