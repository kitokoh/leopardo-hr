<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Actions;

use App\Modules\HR\Domain\Models\OnboardingStep;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Seed the default onboarding checklist for a company.
 *
 * #4929 — source de vérité UNIQUE des étapes d'onboarding (10 étapes,
 * ex-OnboardingStepController::seedDefaultSteps). Les anciens jeux
 * concurrents sont supprimés :
 *  - SeedDefaultSteps legacy (6 étapes add_employees/configure_payroll/…)
 *    était du code mort incompatible avec le contrat consommé par le
 *    wizard web et les apps mobiles ;
 *  - la checklist calculée (8 étapes, OnboardingChecklistController) reste
 *    un endpoint de « go-live readiness » distinct — ce seed alimente la
 *    table `onboarding_steps` consommée par
 *    GET/PATCH /onboarding-setup/*.
 *
 * Appelé au provisioning (CompanyProvisioningService) ET paresseusement par
 * le contrôleur (checklist/complete/skip) pour couvrir les sociétés créées
 * avant ce correctif.
 *
 * #7494 — le seed n'est plus fixe : quand l'entretien de préparation (#7493)
 * est complété, la checklist est GÉNÉRÉE à partir des réponses et des modules
 * actifs (un tenant ne voit jamais une étape sans rapport avec son profil —
 * ex. jamais de kiosque/géofence sans présence terrain). Les tenants sans
 * entretien (historiques, seed paresseux) conservent les 10 étapes par défaut.
 */
final class SeedDefaultSteps
{
    /**
     * #R9 — titres avec accentuation correcte (les anciens étaient sans accent car
     * écrits en ASCII pur, ce qui dégradait l'expérience pour les locales non-FR).
     * #R10 backend — `estimated_minutes` dans metadata (sourcé ONBOARDING_PILOTE.md)
     * : exposé dans OnboardingStepResource pour le frontend et les intégrations.
     *
     * @var array<int, array{key: string, title: string, order: int, required: bool, estimated_minutes: int}>
     */
    private const DEFAULT_STEPS = [
        ['key' => 'company_info',        'title' => 'Renseigner les informations entreprise', 'order' => 1,  'required' => true,  'estimated_minutes' => 3],
        ['key' => 'first_department',    'title' => 'Créer le premier département',            'order' => 2,  'required' => true,  'estimated_minutes' => 2],
        ['key' => 'first_employee',      'title' => 'Ajouter le premier employé',              'order' => 3,  'required' => true,  'estimated_minutes' => 6],
        ['key' => 'first_attendance',    'title' => 'Effectuer le premier pointage',           'order' => 4,  'required' => true,  'estimated_minutes' => 3],
        ['key' => 'invite_manager',      'title' => 'Inviter un gestionnaire',                 'order' => 5,  'required' => false, 'estimated_minutes' => 3],
        ['key' => 'configure_schedules', 'title' => 'Configurer les horaires',                 'order' => 6,  'required' => true,  'estimated_minutes' => 3],
        ['key' => 'first_report',        'title' => 'Générer le premier rapport mensuel',      'order' => 7,  'required' => false, 'estimated_minutes' => 2],
        ['key' => 'configure_payroll',   'title' => 'Configurer la paie',                      'order' => 8,  'required' => false, 'estimated_minutes' => 4],
        ['key' => 'install_kiosk',       'title' => 'Installer un kiosque',                    'order' => 9,  'required' => false, 'estimated_minutes' => 5],
        ['key' => 'activate_geofence',   'title' => 'Activer le géofence',                     'order' => 10, 'required' => false, 'estimated_minutes' => 2],
    ];

    public function execute(string $companyId): void
    {
        $steps = $this->stepsFor($companyId);
        $desiredKeys = array_map(static fn (array $step): string => $step['key'], $steps);

        // #4188 : la dédup lit la colonne réelle `step_key` (l'attribut
        // `key` n'existe pas sur le modèle → pluck('key') renvoyait [null]).
        $existing = OnboardingStep::where('company_id', $companyId)->pluck('step_key')->toArray();

        DB::transaction(function () use ($companyId, $existing, $steps, $desiredKeys): void {
            // #7494 — convergence après entretien : les étapes génériques
            // encore `pending` qui ne font pas partie du profil sont retirées
            // (les étapes déjà complétées/sautées — actions réelles de
            // l'utilisateur — sont TOUJOURS conservées).
            if ($this->interviewCompleted($companyId)) {
                OnboardingStep::where('company_id', $companyId)
                    ->where('status', 'pending')
                    ->whereNotIn('step_key', $desiredKeys)
                    ->delete();
            }

            foreach ($steps as $step) {
                if (! in_array($step['key'], $existing, true)) {
                    // #4188 : `key`/`label` ne sont pas fillable et `label`
                    // n'est pas une colonne — mapper sur step_key/title, sinon
                    // chaque étape est insérée avec step_key/title NULL.
                    OnboardingStep::create([
                        'company_id' => $companyId,
                        'step_key' => $step['key'],
                        'title' => $step['title'],
                        'order' => $step['order'],
                        'required' => $step['required'],
                        'status' => 'pending',
                        // #R10 backend — stockage de l'estimation de durée pour
                        // le frontend et les intégrations OpenAPI.
                        'metadata' => ['estimated_minutes' => $step['estimated_minutes']],
                    ]);
                }
            }
        });
    }

    /**
     * Étapes à seeder pour cette société : générées depuis l'entretien de
     * préparation quand il est complété (#7494), sinon les 10 étapes par
     * défaut (tenants historiques — aucun changement de comportement).
     *
     * @return array<int, array{key: string, title: string, order: int, required: bool, estimated_minutes: int}>
     */
    private function stepsFor(string $companyId): array
    {
        // FQN volontaire (pas d'import) : l'allowlist de la garde de pureté
        // des couches épingle `SeedDefaultSteps.php:8` (facade DB) — un import
        // supplémentaire décalerait la ligne et casserait la garde (#6568).
        $company = \App\Core\Tenant\Domain\Models\Company::query()->find($companyId);

        if (! $company instanceof \App\Core\Tenant\Domain\Models\Company) {
            return self::DEFAULT_STEPS;
        }

        $interview = $this->interview($company);

        if (($interview['status'] ?? null) !== 'completed') {
            return self::DEFAULT_STEPS;
        }

        $answers = is_array($interview['answers'] ?? null) ? $interview['answers'] : [];

        return $this->personalizedSteps($company, $answers);
    }

    private function interviewCompleted(string $companyId): bool
    {
        $company = \App\Core\Tenant\Domain\Models\Company::query()->find($companyId);

        return $company instanceof \App\Core\Tenant\Domain\Models\Company
            && ($this->interview($company)['status'] ?? null) === 'completed';
    }

    /**
     * @return array<string, mixed>
     */
    private function interview(\App\Core\Tenant\Domain\Models\Company $company): array
    {
        $metadata = $company->metadata ?? [];

        return is_array($metadata['setup_interview'] ?? null) ? $metadata['setup_interview'] : [];
    }

    /**
     * #7494 — checklist GÉNÉRÉE par profil. Règles (issue) :
     *  - restaurateur avec employés → premier employé, horaires, premier
     *    pointage ;
     *  - solo vitrine → personnaliser le site, publier — aucun outil d'équipe ;
     *  - JAMAIS de kiosque/géofence sans présence terrain (locaux physiques
     *    déclarés ET présence active).
     *
     * Un tenant ne voit jamais une étape sans rapport avec ses modules actifs
     * (critère 1) ; `company_info` reste le point de départ commun (la liste
     * n'est jamais vide — le seed paresseux du contrôleur boucle sinon).
     *
     * @param  array<string, mixed>  $answers
     * @return array<int, array{key: string, title: string, order: int, required: bool, estimated_minutes: int}>
     */
    private function personalizedSteps(\App\Core\Tenant\Domain\Models\Company $company, array $answers): array
    {
        $modules = $company->moduleSelection() ?? [];
        $tool = static fn (string $key): bool => ($modules[$key] ?? false) === true;

        $solo = ($answers['company_type'] ?? null) === 'solo';
        $team = ! $solo;
        $premises = $answers['premises'] ?? null;
        $fieldPresence = in_array($premises, ['single', 'multiple'], true);
        $showcase = $tool('showcase') || $company->hasFeature('company_showcase');

        // Titres sourcés du catalogue api/lang/*/onboarding.php (garde i18n
        // PA2-I18N-007 / #5432 : aucun message français en dur dans les Actions).
        $steps = [
            ['key' => 'company_info', 'title' => __('onboarding.step_company_info'), 'required' => true, 'estimated_minutes' => 3],
        ];

        if ($team && $tool('employees')) {
            $steps[] = ['key' => 'first_employee', 'title' => __('onboarding.step_first_employee'), 'required' => true, 'estimated_minutes' => 6];
        }

        if ($team && ($answers['scheduled_hours'] ?? null) === 'yes' && $tool('attendance')) {
            $steps[] = ['key' => 'configure_schedules', 'title' => __('onboarding.step_configure_schedules'), 'required' => true, 'estimated_minutes' => 3];
        }

        if ($team && $tool('attendance')) {
            $steps[] = ['key' => 'first_attendance', 'title' => __('onboarding.step_first_attendance'), 'required' => true, 'estimated_minutes' => 3];
        }

        if ($tool('payroll')) {
            $steps[] = ['key' => 'configure_payroll', 'title' => __('onboarding.step_configure_payroll'), 'required' => false, 'estimated_minutes' => 4];
        }

        // Présence terrain uniquement : un tenant sans lieu physique (équipes
        // mobiles, pas de local) ne voit JAMAIS kiosque ni géofence.
        if ($team && $tool('attendance') && $fieldPresence) {
            $steps[] = ['key' => 'install_kiosk', 'title' => __('onboarding.step_install_kiosk'), 'required' => false, 'estimated_minutes' => 5];
            $steps[] = ['key' => 'activate_geofence', 'title' => __('onboarding.step_activate_geofence'), 'required' => false, 'estimated_minutes' => 2];
        }

        if ($showcase) {
            $steps[] = ['key' => 'customize_showcase', 'title' => __('onboarding.step_customize_showcase'), 'required' => true, 'estimated_minutes' => 5];
            $steps[] = ['key' => 'publish_showcase', 'title' => __('onboarding.step_publish_showcase'), 'required' => false, 'estimated_minutes' => 2];
        }

        // #7640 — checklist orientée agence de voyage : le gérant qui s'est
        // déclaré « agence » à l'entretien (verticale `travelagency` activée
        // par `CompleteSetupInterview` → feature flag tenant) démarre par son
        // réseau, son premier voyage et sa première vente. Étapes déclaratives
        // (aucun prédicat `StepCompletionGuard`) : vérifier l'existence de
        // gares/voyages exigerait un import Onboarding → TravelAgency,
        // interdit par la garde d'isolation inter-modules (#5584).
        if ($company->hasFeature('travelagency')) {
            $steps[] = ['key' => 'travel_setup_network', 'title' => __('onboarding.step_travel_setup_network'), 'required' => true, 'estimated_minutes' => 8];
            $steps[] = ['key' => 'travel_first_trip', 'title' => __('onboarding.step_travel_first_trip'), 'required' => true, 'estimated_minutes' => 5];
            $steps[] = ['key' => 'travel_first_sale', 'title' => __('onboarding.step_travel_first_sale'), 'required' => false, 'estimated_minutes' => 4];
        }

        $ordered = [];
        $order = 1;
        foreach ($steps as $step) {
            $ordered[] = [
                'key' => $step['key'],
                'title' => $step['title'],
                'order' => $order,
                'required' => $step['required'],
                'estimated_minutes' => $step['estimated_minutes'],
            ];
            $order++;
        }

        return $ordered;
    }
}
