<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Actions;

use App\Models\OnboardingStep;
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
        $existing = OnboardingStep::where('company_id', $companyId)->pluck('key')->toArray();

        DB::transaction(function () use ($companyId, $existing): void {
            foreach (self::DEFAULT_STEPS as $step) {
                if (! in_array($step['key'], $existing, true)) {
                    OnboardingStep::create([
                        'company_id' => $companyId,
                        'key'        => $step['key'],
                        'label'      => $step['label'],
                        'order'      => $step['order'],
                        'status'     => 'pending',
                    ]);
                }
            }
        });
    }
}
