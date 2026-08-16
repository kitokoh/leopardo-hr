<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Actions;

use App\Modules\HR\Domain\Models\OnboardingStep;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Seed the default onboarding checklist for a company.
 */
final class SeedDefaultSteps
{
    private const DEFAULT_STEPS = [
        ['key' => 'add_employees',     'label' => 'Ajouter des employés',           'order' => 1],
        ['key' => 'configure_payroll', 'label' => 'Configurer la paie',              'order' => 2],
        ['key' => 'setup_schedules',   'label' => 'Définir les horaires',            'order' => 3],
        ['key' => 'setup_geofence',    'label' => 'Configurer la géolocalisation',   'order' => 4],
        ['key' => 'setup_kiosk',       'label' => 'Activer le kiosque biométrique',  'order' => 5],
        ['key' => 'first_checkin',     'label' => 'Premier pointage effectué',       'order' => 6],
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
                        'title' => $step['label'],
                        'order' => $step['order'],
                        'status' => 'pending',
                    ]);
                }
            }
        });
    }
}
