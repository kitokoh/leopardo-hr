<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DatabaseSeeder — Orchestrateur principal des seeders
 *
 * ORDRE D'EXÉCUTION OBLIGATOIRE (respecter les dépendances FK) :
 * 1. PlanSeeder         → plans (schéma public)
 * 2. LanguageSeeder     → languages (schéma public)
 * 3. HrModelSeeder      → hr_model_templates (schéma public)
 * 4. SuperAdminSeeder   → super_admins (schéma public)
 * 5. DemoCompanySeeder  → company de démo + employees (mode local uniquement)
 *
 * USAGE :
 *   php artisan db:seed                    → Seeders de base (prod-safe)
 *   php artisan db:seed --class=DemoCompanySeeder   → Données de démo (dev only)
 *
 * NOTE MULTI-TENANT :
 *   Les seeders ci-dessous opèrent UNIQUEMENT sur le schéma public.
 *   Les données tenant (employees, attendance, etc.) sont créées via :
 *   - TenantService::createTenant() lors de POST /public/register
 *   - DemoCompanySeeder (environnement local uniquement)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // S'assurer qu'on est sur le schéma public pour les seeders de base
        DB::statement('SET search_path TO public');

        $this->command->info('');
        $this->command->info('🐆 LEOPARDO RH — Initialisation de la base de données');
        $this->command->info('═══════════════════════════════════════════════════');

        $this->call([
            PlanSeeder::class,      // Plans tarifaires (Starter/Business/Enterprise)
            LanguageSeeder::class,  // Langues (fr/ar/en/tr)
            HrModelSeeder::class,   // Modèles RH par pays (DZ/MA/TN/FR/TR)
            SuperAdminSeeder::class, // Premier Super Admin
        ]);

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════');
        $this->command->info('✅ Base de données initialisée avec succès !');
        $this->command->info('');
        $this->command->info('Prochaines étapes :');
        $this->command->info('  1. Configurer Nginx (nginx-api.conf)');
        $this->command->info('  2. Configurer Supervisor (leopardo-horizon.supervisor.conf)');
        $this->command->info('  3. php artisan horizon:start');
        $this->command->info('  4. Tester : GET /api/health');

        // En environnement local : proposer les données de démo
        if (app()->environment('local', 'development')) {
            $this->command->info('');
            $this->command->info('💡 Environnement local détecté');
            $this->command->info('   Pour créer une company de démo avec des données :');
            $this->command->info('   php artisan db:seed --class=DemoCompanySeeder');
        }
    }
}
