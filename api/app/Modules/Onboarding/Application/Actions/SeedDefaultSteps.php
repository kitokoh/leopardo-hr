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
 */
final class SeedDefaultSteps
{
    /**
     * @var array<int, array{key: string, title: string, order: int, required: bool}>
     */
    private const DEFAULT_STEPS = [
        ['key' => 'company_info',        'title' => 'Renseigner les informations entreprise', 'order' => 1, 'required' => true],
        ['key' => 'first_department',    'title' => 'Creer le premier departement',            'order' => 2, 'required' => true],
        ['key' => 'first_employee',      'title' => 'Ajouter le premier employe',              'order' => 3, 'required' => true],
        ['key' => 'first_attendance',    'title' => 'Effectuer le premier pointage',           'order' => 4, 'required' => true],
        ['key' => 'invite_manager',      'title' => 'Inviter un manager',                      'order' => 5, 'required' => false],
        ['key' => 'configure_schedules', 'title' => 'Configurer les horaires',                 'order' => 6, 'required' => true],
        ['key' => 'first_report',        'title' => 'Generer le premier rapport mensuel',      'order' => 7, 'required' => false],
        ['key' => 'configure_payroll',   'title' => 'Configurer la paie',                      'order' => 8, 'required' => false],
        ['key' => 'install_kiosk',       'title' => 'Installer un kiosk',                      'order' => 9, 'required' => false],
        ['key' => 'activate_geofence',   'title' => 'Activer le geofence',                     'order' => 10, 'required' => false],
    ];

    public function execute(string $companyId): void
    {
        // #4188 : la dédup lit la colonne réelle `step_key` (l'attribut
        // `key` n'existe pas sur le modèle → pluck('key') renvoyait [null]).
        $existing = OnboardingStep::where('company_id', $companyId)->pluck('step_key')->toArray();

        DB::transaction(function () use ($companyId, $existing): void {
            foreach (self::DEFAULT_STEPS as $step) {
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
                    ]);
                }
            }
        });
    }
}
