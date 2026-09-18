<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7494 — checklist d'onboarding GÉNÉRÉE par profil (entretien #7493).
 *
 * Critère 1 de l'issue : un tenant ne voit JAMAIS une étape sans rapport avec
 * ses modules actifs — vérifié pour les 3 profils types :
 *  - restaurateur avec employés → premier employé, horaires, premier pointage ;
 *  - solo vitrine → personnaliser le site, publier — aucun outil d'équipe ;
 *  - pas de présence terrain → JAMAIS kiosque ni géofence.
 *
 * Les tenants SANS entretien conservent les 10 étapes par défaut
 * (`SeedDefaultStepsTest`, inchangé) ; les étapes déjà complétées/sautées
 * sont conservées lors de la resynchronisation.
 */
class SetupInterviewSeedingTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, bool>  $modules
     * @param  array<string, bool>  $features
     */
    private function companyWithInterview(array $answers, array $modules = [], array $features = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => [
                'modules' => $modules,
                'setup_interview' => [
                    'status' => 'completed',
                    'answers' => $answers,
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
            'features' => $features,
        ]);

        return $company;
    }

    /** @return list<string> */
    private function seededKeys(Company $company): array
    {
        (new SeedDefaultSteps)->execute((string) $company->id);

        $keys = OnboardingStep::where('company_id', $company->id)
            ->orderBy('order')
            ->pluck('step_key')
            ->all();

        // `pluck()` renvoie array<mixed> pour PHPStan strict : on ne garde
        // que les chaines pour honorer le contrat list<string>.
        return array_values(array_filter($keys, static fn ($key): bool => is_string($key)));
    }

    public function test_profil_restaurateur_avec_employes(): void
    {
        $company = $this->companyWithInterview(
            answers: [
                'company_type' => 'team',
                'team_size' => '11-50',
                'sector' => 'restaurant',
                'premises' => 'single',
                'scheduled_hours' => 'yes',
            ],
            modules: ['employees' => true, 'attendance' => true],
            features: ['restaurant' => true, 'attendance' => true],
        );

        $keys = $this->seededKeys($company);

        // Les actions de mise en route citées par l'issue sont présentes.
        $this->assertContains('first_employee', $keys);
        $this->assertContains('configure_schedules', $keys);
        $this->assertContains('first_attendance', $keys);
        // Présence terrain déclarée → le kiosque est proposé (optionnel).
        $this->assertContains('install_kiosk', $keys);
        // Aucune étape hors profil : pas de vitrine, pas de paie non choisie.
        $this->assertNotContains('customize_showcase', $keys);
        $this->assertNotContains('configure_payroll', $keys);
    }

    public function test_profil_solo_vitrine(): void
    {
        $company = $this->companyWithInterview(
            answers: [
                'company_type' => 'solo',
                'sector' => 'services',
                'priorities' => ['showcase'],
            ],
            modules: ['showcase' => true],
            features: ['company_showcase' => true],
        );

        $keys = $this->seededKeys($company);

        $this->assertContains('customize_showcase', $keys);
        $this->assertContains('publish_showcase', $keys);
        // Aucun outil d'équipe pour un indépendant.
        foreach (['first_employee', 'first_department', 'invite_manager', 'first_attendance', 'configure_schedules', 'install_kiosk', 'activate_geofence'] as $teamStep) {
            $this->assertNotContains($teamStep, $keys, "Étape d'équipe interdite pour un solo : {$teamStep}");
        }
    }

    public function test_profil_sans_presence_terrain_n_a_jamais_kiosque_ni_geofence(): void
    {
        foreach (['mobile', 'none'] as $premises) {
            $company = $this->companyWithInterview(
                answers: [
                    'company_type' => 'team',
                    'team_size' => '1-10',
                    'sector' => 'services',
                    'premises' => $premises,
                    'scheduled_hours' => 'yes',
                ],
                modules: ['employees' => true, 'attendance' => true],
            );

            $keys = $this->seededKeys($company);

            $this->assertNotContains('install_kiosk', $keys, "premises={$premises}");
            $this->assertNotContains('activate_geofence', $keys, "premises={$premises}");
            // Les étapes d'équipe pertinentes restent là.
            $this->assertContains('first_employee', $keys);
            $this->assertContains('first_attendance', $keys);
        }
    }

    public function test_resync_apres_entretien_conserve_les_actions_deja_faites(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => [],
        ]);

        // Seed générique au provisioning (10 étapes), puis une action réelle.
        (new SeedDefaultSteps)->execute((string) $company->id);
        OnboardingStep::where('company_id', $company->id)
            ->where('step_key', 'install_kiosk')
            ->update(['status' => 'completed']);

        // L'entretien arrive APRÈS : profil sans présence terrain.
        $metadata = $company->metadata ?? [];
        $metadata['modules'] = ['employees' => true, 'attendance' => true];
        $metadata['setup_interview'] = [
            'status' => 'completed',
            'answers' => [
                'company_type' => 'team',
                'sector' => 'services',
                'premises' => 'none',
            ],
        ];
        $company->update(['metadata' => $metadata]);

        $keys = $this->seededKeys($company);

        // Les étapes génériques `pending` hors profil sont retirées…
        $this->assertNotContains('activate_geofence', $keys);
        $this->assertNotContains('first_report', $keys);
        // …mais l'action déjà FAITE est conservée (jamais de perte d'historique).
        $this->assertContains('install_kiosk', $keys);
        $this->assertContains('first_employee', $keys);
    }

    public function test_sans_entretien_le_seed_par_defaut_est_inchange(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
        ]);

        $keys = $this->seededKeys($company);

        $this->assertCount(10, $keys);
        $this->assertContains('install_kiosk', $keys);
    }
}
