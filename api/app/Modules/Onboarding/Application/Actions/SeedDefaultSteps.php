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
                        'step_key'   => $step['key'],
                        'title'      => $step['title'],
                        'order'      => $step['order'],
                        'required'   => $step['required'],
                        'status'     => 'pending',
                        // #R10 backend — stockage de l'estimation de durée pour
                        // le frontend et les intégrations OpenAPI.
                        'metadata'   => ['estimated_minutes' => $step['estimated_minutes']],
                    ]);
                }
            }
        });
    }
}
