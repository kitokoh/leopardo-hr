<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PlanSeeder — source de vérité des plans tarifaires publics.
 *
 * Les codes métier sont consommés par le checkout et la matrice de
 * fonctionnalités ; les noms affichés restent Free/Pilot/Operations/Enterprise.
 */
class PlanSeeder extends Seeder
{
    /** @var array<string, string> */
    private const LEGACY_NAMES = [
        'Starter' => 'Pilot',
        'Business' => 'Operations',
    ];

    public function run(): void
    {
        DB::statement('SET search_path TO public');
        DB::transaction(function (): void {
            $this->migrateLegacyPlanNames();

            foreach ($this->plans() as $plan) {
                DB::table('plans')->updateOrInsert(
                    ['name' => $plan['name']],
                    $plan,
                );
            }
        });

        $this->command->info('Plans créés : Free (0€/5emp), Pilot (29€/30emp), Operations (99€/250emp), Enterprise (sur devis).');
    }

    /**
     * Preserve existing plan IDs when the old seed has already run. If both
     * names exist, move company references before removing the duplicate.
     */
    private function migrateLegacyPlanNames(): void
    {
        foreach (self::LEGACY_NAMES as $legacyName => $canonicalName) {
            $legacyId = DB::table('plans')->where('name', $legacyName)->value('id');
            if ($legacyId === null) {
                continue;
            }

            $canonicalId = DB::table('plans')->where('name', $canonicalName)->value('id');
            if ($canonicalId === null) {
                DB::table('plans')->where('id', $legacyId)->update(['name' => $canonicalName]);

                continue;
            }

            DB::table('companies')->where('plan_id', $legacyId)->update(['plan_id' => $canonicalId]);
            DB::table('plans')->where('id', $legacyId)->delete();
        }
    }

    /** @return list<array<string, mixed>> */
    private function plans(): array
    {
        return [
            [
                'name' => 'Free',
                'price_monthly' => 0.00,
                'price_yearly' => 0.00,
                'max_employees' => 5,
                'trial_days' => (int) config('billing.trial_days'),
                'is_active' => true,
                'features' => json_encode([
                    'biometric' => false,
                    'tasks' => false,
                    'advanced_reports' => false,
                    'excel_export' => false,
                    'bank_export' => false,
                    'billing_auto' => false,
                    'multi_managers' => false,
                    'photo_attendance' => false,
                    'api_public' => false,
                    'evaluations' => false,
                    'schema_isolation' => false,
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'name' => 'Pilot',
                'price_monthly' => 29.00,
                'price_yearly' => 290.00,
                'max_employees' => 30,
                'trial_days' => (int) config('billing.trial_days'),
                'is_active' => true,
                'features' => json_encode([
                    'biometric' => false,
                    'tasks' => false,
                    'advanced_reports' => false,
                    'excel_export' => true,
                    'bank_export' => false,
                    'billing_auto' => false,
                    'multi_managers' => false,
                    'photo_attendance' => false,
                    'api_public' => false,
                    'evaluations' => false,
                    'schema_isolation' => false,
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'name' => 'Operations',
                'price_monthly' => 99.00,
                'price_yearly' => 948.00,
                'max_employees' => 250,
                'trial_days' => (int) config('billing.trial_days'),
                'is_active' => true,
                'features' => json_encode([
                    'biometric' => true,
                    'tasks' => true,
                    'advanced_reports' => true,
                    'excel_export' => true,
                    'bank_export' => true,
                    'billing_auto' => true,
                    'multi_managers' => true,
                    'photo_attendance' => true,
                    'api_public' => false,
                    'evaluations' => true,
                    'schema_isolation' => false,
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'name' => 'Enterprise',
                'price_monthly' => 0.00,
                'price_yearly' => 0.00,
                'max_employees' => null,
                'trial_days' => (int) config('billing.trial_days'),
                'is_active' => true,
                'features' => json_encode([
                    'biometric' => true,
                    'tasks' => true,
                    'advanced_reports' => true,
                    'excel_export' => true,
                    'bank_export' => true,
                    'billing_auto' => true,
                    'multi_managers' => true,
                    'photo_attendance' => true,
                    'api_public' => true,
                    'evaluations' => true,
                    'schema_isolation' => true,
                ], JSON_THROW_ON_ERROR),
            ],
        ];
    }
}
