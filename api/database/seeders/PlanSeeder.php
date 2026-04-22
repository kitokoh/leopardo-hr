<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PlanSeeder — Crée les 3 plans tarifaires
 *
 * Source de vérité : docs/dossierdeConception/03_modele_economique/03_MODELE_ECONOMIQUE.md
 *
 * DÉCISIONS :
 * - excel_export = true pour Starter (corrigé v3.1 — était false)
 * - evaluations + schema_isolation inclus dans tous les plans
 * - Trial est géré via companies.status='trial', pas un plan séparé
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET search_path TO public');

        $plans = [
            [
                'name' => 'Starter',
                'price_monthly' => 29.00,
                'price_yearly' => 290.00,     // -17% vs mensuel
                'max_employees' => 20,
                'trial_days' => 14,
                'is_active' => true,
                'features' => json_encode([
                    'biometric' => false,
                    'tasks' => false,
                    'advanced_reports' => false,
                    'excel_export' => true,   // ✅ CORRECTION v3.1 — était false
                    'bank_export' => false,
                    'billing_auto' => false,
                    'multi_managers' => false,
                    'photo_attendance' => false,
                    'api_public' => false,
                    'evaluations' => false,
                    'schema_isolation' => false,
                ]),
            ],
            [
                'name' => 'Business',
                'price_monthly' => 79.00,
                'price_yearly' => 790.00,
                'max_employees' => 200,
                'trial_days' => 14,
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
                    'schema_isolation' => false,  // Schéma partagé (shared_tenants)
                ]),
            ],
            [
                'name' => 'Enterprise',
                'price_monthly' => 199.00,
                'price_yearly' => 1990.00,
                'max_employees' => null,         // NULL = illimité
                'trial_days' => 30,           // Trial plus long pour Enterprise
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
                    'schema_isolation' => true,   // Schéma PostgreSQL dédié
                ]),
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('plans')->updateOrInsert(
                ['name' => $plan['name']],
                $plan
            );
        }

        $this->command->info('✅ Plans créés : Starter (29€/20emp), Business (79€/200emp), Enterprise (199€/illimité)');
    }
}
