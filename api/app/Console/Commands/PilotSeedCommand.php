<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Seed\PilotSeedGuard;
use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\Concerns\GuardsPilotSeeding;
use Database\Seeders\CrmPilotSeeder;
use Database\Seeders\EduManagerPilotSeeder;
use Database\Seeders\FuelStationPilotSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MAT-012 (#5870) — Seeds pilotes par verticale (BC-01 PLATFORM).
 *
 * Commande UNIQUE pour `pilot:seed` (consolidation #8004) : deux classes
 * racine portaient le même nom — `PilotSeedCommand` (`--solution=fuel|edu`
 * + `--clean`) et `SeedPilotCommand` (`{vertical}` + `--force`, verticale
 * `crm`) — donc un comportement dépendant de l'ordre d'enregistrement.
 * L'unique implémentation accepte désormais les DEUX formes :
 *
 *   php artisan pilot:seed crm
 *   php artisan pilot:seed --solution=fuel
 *   php artisan pilot:seed --solution=edu --clean
 *   php artisan pilot:seed crm --force
 *
 * Les seeds sont idempotents (skip si présent), nettoyables (`--clean`) et ne
 * peuvent jamais cibler un tenant de production par erreur (gardes
 * {@see GuardsPilotSeeding} + {@see PilotSeedGuard}, allowlist de slugs).
 */
class PilotSeedCommand extends Command
{
    use GuardsPilotSeeding;

    protected $signature = 'pilot:seed
        {vertical? : verticale pilote à semer (crm|fuel|edu)}
        {--solution= : alias de {vertical} (fuel|edu|crm) — compatibilité MAT-012}
        {--clean : supprime le tenant pilote au lieu de le créer}
        {--force : autorise l\'exécution hors environnement pilote/demo}';

    protected $description = 'Crée ou nettoie les seeds pilotes synthétiques par solution (MAT-012 #5870).';

    /**
     * @var array<string, array{seeder: class-string, slugs: list<string>, tenant_tables: list<string>}>
     */
    private const VERTICALS = [
        'crm' => [
            'seeder' => CrmPilotSeeder::class,
            'slugs' => ['crm-pilot-alpha', 'crm-pilot-beta'],
            'tenant_tables' => ['crm_tasks', 'crm_opportunities', 'crm_leads', 'crm_pipelines', 'crm_contacts', 'crm_accounts'],
        ],
        'fuel' => [
            'seeder' => FuelStationPilotSeeder::class,
            'slugs' => [FuelStationPilotSeeder::SLUG],
            'tenant_tables' => [
                'fuel_sales', 'fuel_cash_session_movements', 'fuel_cash_sessions',
                'fuel_meter_intervals', 'fuel_meter_readings', 'fuel_meter_registers',
                'fuel_shift_assignments', 'fuel_shifts', 'fuel_tanks', 'fuel_pumps',
                'fuel_products', 'fuel_sites', 'fuel_stations',
            ],
        ],
        'edu' => [
            'seeder' => EduManagerPilotSeeder::class,
            'slugs' => [EduManagerPilotSeeder::SLUG],
            'tenant_tables' => [
                'edu_student_guardians', 'edu_students', 'edu_guardians', 'edu_campuses',
            ],
        ],
    ];

    public function handle(PilotSeedGuard $guard): int
    {
        $vertical = $this->resolveVertical();

        $config = self::VERTICALS[$vertical] ?? null;

        if ($config === null) {
            $this->error('Verticale inconnue ['.$vertical.']. Connues : '.implode(', ', array_keys(self::VERTICALS)));

            return self::FAILURE;
        }

        try {
            $this->assertPilotEnvironmentAllowed($vertical);
            $guard->assertEnvironment((string) app()->environment(), (bool) $this->option('force'));

            foreach ($config['slugs'] as $slug) {
                $guard->assertPilotSlug($slug);
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('clean')) {
            return $this->cleanPilot($vertical, $config['slugs'], $config['tenant_tables']);
        }

        return $this->seedPilot($vertical, $config['seeder']);
    }

    /**
     * `{vertical}` (positionnel) prime sur `--solution` (alias historique) ;
     * sans argument, la verticale par défaut reste `fuel` (comportement
     * documenté de `pilot:seed --solution=fuel`).
     */
    private function resolveVertical(): string
    {
        $argument = $this->argument('vertical');

        if (is_string($argument) && trim($argument) !== '') {
            return trim($argument);
        }

        $solution = $this->option('solution');

        if (is_string($solution) && trim($solution) !== '') {
            return trim($solution);
        }

        return 'fuel';
    }

    /**
     * @param  class-string  $seeder
     */
    private function seedPilot(string $vertical, string $seeder): int
    {
        // Déterministe : les tenants pilotes vivent dans le schéma `public`
        // (les données tenant sont créées via withinTenant par les seeders).
        // Le search_path d'origine est RESTAURÉ après le seed : sans cela, un
        // test qui enchaîne des requêtes `shared_tenants` après l'appel
        // artisan casserait (relation "…" does not exist).
        $originalSearchPath = DB::getDriverName() === 'pgsql'
            ? (string) (DB::selectOne('SHOW search_path')->search_path ?? '')
            : '';

        if ($originalSearchPath !== '') {
            DB::statement('SET search_path TO public');
        }

        try {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        } finally {
            if ($originalSearchPath !== '') {
                DB::statement("SET search_path TO {$originalSearchPath}");
            }
        }

        $this->info("Seeds pilotes [{$vertical}] appliqués (idempotent).");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $slugs
     * @param  list<string>  $tenantTables
     */
    private function cleanPilot(string $vertical, array $slugs, array $tenantTables): int
    {
        $this->assertPilotEnvironmentAllowed('clean:'.$vertical);

        foreach ($slugs as $slug) {
            /** @var Company|null $company */
            $company = Company::query()->where('slug', $slug)->first();

            if (! $company instanceof Company) {
                $this->warn("Tenant pilote {$slug} absent — rien à nettoyer.");

                continue;
            }

            $companyId = (string) $company->id;

            DB::transaction(function () use ($companyId, $company, $tenantTables): void {
                // Suppression des lignes tenant du pilote (tables connues de la
                // verticale), puis de la société publique. Les tables absentes
                // sont ignorées.
                foreach ($tenantTables as $table) {
                    if (schemaTableExists($table)) {
                        DB::table($table)->where('company_id', $companyId)->delete();
                    }
                }

                if (schemaTableExists('employees')) {
                    DB::table('employees')->where('company_id', $companyId)->delete();
                }

                $company->delete();
            });

            $this->info("Tenant pilote {$slug} supprimé (données synthétiques nettoyées).");
        }

        return self::SUCCESS;
    }
}
