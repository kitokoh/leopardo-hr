<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Support\Gdpr\CrmRgpdRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * crm:anonymize — anonymisation RGPD idempotente et rejouable des données
 * PII du CRM client (issue #5739).
 *
 * Sécurité :
 *  - `--dry-run` par défaut : n'écrit RIEN sans `--force` ;
 *  - tenant obligatoire (`--company=<uuid>` ou `--all`) : jamais global ;
 *  - remplacements DÉTERMINISTES (même entrée → même sortie) : rejouable et
 *    idempotent (relancer ne change rien) ;
 *  - tables absentes (socle V0 pas encore mergé) : ignorées proprement avec
 *    journalisation — la commande s'active seule au merge des migrations ;
 *  - aucun secret ni PII dans les logs (seuls table/rows/company_id).
 *
 * Usage :
 *   php artisan crm:anonymize --company=<uuid> --dry-run
 *   php artisan crm:anonymize --company=<uuid> --force
 *   php artisan crm:anonymize --company=<uuid> --table=crm_contacts --force
 *   php artisan crm:anonymize --all --force
 */
final class CrmAnonymizeCommand extends Command
{
    protected $signature = 'crm:anonymize
        {--company= : UUID du tenant (obligatoire sauf --all)}
        {--all : Anonymiser tous les tenants actifs}
        {--table= : Restreindre à une table du registre}
        {--dry-run : Mode simulation (défaut : aucune écriture)}
        {--force : Réaliser réellement l\'anonymisation}';

    protected $description = 'Anonymise les données PII du CRM client (registre RGPD #5739)';

    public function handle(TenantManager $tenantManager): int
    {
        if ($this->option('force') && $this->option('dry-run')) {
            $this->error('--force et --dry-run sont mutuellement exclus.');

            return self::FAILURE;
        }

        $companies = $this->resolveCompanies();
        if ($companies === []) {
            $this->error('Aucun tenant ciblé : passer --company=<uuid> ou --all.');

            return self::FAILURE;
        }

        $onlyTable = $this->option('table');
        $onlyTable = is_string($onlyTable) && $onlyTable !== '' ? $onlyTable : null;

        if ($onlyTable !== null && CrmRgpdRegistry::entryForTable($onlyTable) === null) {
            $this->error("Table « {$onlyTable} » inconnue du registre RGPD CRM.");

            return self::FAILURE;
        }

        $dryRun = ! $this->option('force');
        $total = 0;

        foreach ($companies as $company) {
            $total += $this->anonymizeCompany($tenantManager, $company, $onlyTable, $dryRun);
        }

        $mode = $dryRun ? 'dry-run (aucune écriture)' : 'écrit';
        $this->info("Anonymisation terminée en {$mode} : {$total} ligne(s) traitées.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, Company>
     */
    private function resolveCompanies(): array
    {
        $companyId = $this->option('company');
        $companyId = is_string($companyId) && $companyId !== '' ? $companyId : null;

        if ($companyId !== null) {
            /** @var Company|null $company */
            $company = Company::query()->find($companyId);

            return $company instanceof Company ? [$company] : [];
        }

        if ($this->option('all')) {
            return Company::query()->where('status', 'active')->get()->all();
        }

        return [];
    }

    private function anonymizeCompany(TenantManager $tenantManager, Company $company, ?string $onlyTable, bool $dryRun): int
    {
        $result = $tenantManager->withinTenant($company, function () use ($company, $onlyTable, $dryRun): int {
            $total = 0;

            foreach (array_keys(CrmRgpdRegistry::entries()) as $table) {
                if (is_string($onlyTable) && $onlyTable !== '' && $table !== $onlyTable) {
                    continue;
                }

                $total += $this->anonymizeTable($company, $table, $dryRun);
            }

            return $total;
        });

        return $result;
    }

    private function anonymizeTable(Company $company, string $table, bool $dryRun): int
    {
        if (! Schema::hasTable($table)) {
            $this->line("  [skip] {$table} : table absente (socle V0 non mergé) — ignorée proprement.");
            Log::channel('audit')->info('crm.anonymisation.skipped', [
                'table' => $table,
                'company_id' => $company->id,
                'reason' => 'table_absent',
            ]);

            return 0;
        }

        $piiColumns = CrmRgpdRegistry::piiColumns($table);
        if ($piiColumns === []) {
            return 0;
        }

        $columns = array_map(static fn ($column): string => (string) $column, Schema::getColumnListing($table));
        $piiColumns = array_intersect_key($piiColumns, array_flip($columns));

        $rows = 0;
        DB::table($table)
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use ($company, $table, $piiColumns, $dryRun, &$rows): void {
                foreach ($chunk as $row) {
                    $values = $this->anonymizedValues($table, $piiColumns, (string) $row->id, (string) $company->id);

                    if (! $dryRun) {
                        DB::table($table)->where('id', $row->id)->update($values);
                    }

                    $rows++;
                }
            });

        if ($rows > 0 || $dryRun) {
            $this->line(sprintf(
                '  [%s] %s : %d ligne(s) %s (tenant %s)',
                $dryRun ? 'simulation' : 'ok',
                $table,
                $rows,
                $dryRun ? 'à anonymiser' : 'anonymisées',
                $company->id
            ));
            Log::channel('audit')->info('crm.anonymisation.completed', [
                'table' => $table,
                'rows' => $rows,
                'company_id' => $company->id,
                'dry_run' => $dryRun,
            ]);
        }

        return $rows;
    }

    /**
     * Valeurs anonymisées déterministes (même entrée → même sortie).
     *
     * @param  array<string, string>  $piiColumns  colonne => type
     * @return array<string, string>
     */
    private function anonymizedValues(string $table, array $piiColumns, string $rowId, string $companyId): array
    {
        $seed = sha1("{$table}|{$rowId}|{$companyId}");

        $values = [];
        foreach ($piiColumns as $column => $type) {
            $values[$column] = match ($type) {
                'email' => 'u'.substr($seed, 0, 12).'@anonymised.invalid',
                'phone' => '+00'.substr($seed, 0, 9),
                'name' => 'Anonyme '.substr($seed, 0, 8),
                default => '[anonymisé-'.substr($seed, 0, 8).']',
            };
        }

        return $values;
    }
}
