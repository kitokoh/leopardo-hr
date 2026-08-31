<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Seed\PilotSeedGuard;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * MAT-012 (#5870) — nettoyage des seeds pilotes.
 *
 * Supprime les tenants pilotes d'une verticale (données CRM + employés +
 * société) sans jamais pouvoir cibler un tenant de production : slugs
 * restreints à l'allowlist `PilotSeedGuard` et refus en production sans
 * `--force`. Idempotent (pilote absent → skip).
 *
 * Usage :
 *   php artisan pilot:cleanup crm
 *   php artisan pilot:cleanup crm --tenant=crm-pilot-alpha
 *   php artisan pilot:cleanup crm --force
 */
final class CleanupPilotCommand extends Command
{
    protected $signature = 'pilot:cleanup {vertical : verticale pilote à nettoyer (crm)}
        {--tenant= : slug pilote cible (défaut : tous les pilotes de la verticale)}
        {--force : autorise l\'exécution hors environnement pilote/demo}';

    protected $description = 'Supprime les données pilotes d\'une verticale (allowlist stricte, garde production)';

    /**
     * @var array<string, array{slugs: list<string>, tenant_tables: list<string>}>
     */
    private const VERTICALS = [
        'crm' => [
            'slugs' => ['crm-pilot-alpha', 'crm-pilot-beta'],
            'tenant_tables' => ['crm_tasks', 'crm_opportunities', 'crm_leads', 'crm_pipelines', 'crm_contacts', 'crm_accounts'],
        ],
    ];

    public function handle(PilotSeedGuard $guard, TenantManager $tenantManager): int
    {
        // Déterministe : les tenants pilotes vivent dans le schéma public.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        $vertical = (string) $this->argument('vertical');

        $config = self::VERTICALS[$vertical] ?? null;

        if ($config === null) {
            $this->error('Verticale inconnue ['.$vertical.']. Connues : '.implode(', ', array_keys(self::VERTICALS)));

            return self::FAILURE;
        }

        try {
            $guard->assertEnvironment((string) app()->environment(), (bool) $this->option('force'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $tenantOption = $this->option('tenant');
        $slugs = is_string($tenantOption) && $tenantOption !== '' ? [$tenantOption] : $config['slugs'];

        $allOk = true;

        foreach ($slugs as $slug) {
            try {
                $guard->assertPilotSlug($slug);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());
                $allOk = false;

                continue;
            }

            $company = Company::query()->where('slug', $slug)->first();

            if (! $company instanceof Company) {
                $this->warn("Pilote [{$slug}] absent — skip (idempotent).");

                continue;
            }

            if (! $this->cleanupTenant($tenantManager, $company, $config['tenant_tables'])) {
                $allOk = false;
            }
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<string>  $tenantTables
     */
    private function cleanupTenant(TenantManager $tenantManager, Company $company, array $tenantTables): bool
    {
        try {
            DB::transaction(function () use ($tenantManager, $company, $tenantTables): void {
                $tenantManager->withinTenant($company, function () use ($company, $tenantTables): void {
                    foreach ($tenantTables as $table) {
                        if (schemaTableExists($table)) {
                            DB::table($table)->where('company_id', $company->id)->delete();
                        }
                    }

                    Employee::query()->where('company_id', $company->id)->delete();
                });

                $company->delete();
            });

            $this->info("Pilote [{$company->slug}] nettoyé.");

            return true;
        } catch (Throwable $exception) {
            $this->error("Pilote [{$company->slug}] : nettoyage partiel — ".$exception->getMessage());

            return false;
        }
    }
}
