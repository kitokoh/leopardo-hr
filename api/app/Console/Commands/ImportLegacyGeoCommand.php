<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Infrastructure\Services\LegacyGeoImportService;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

/**
 * leopardo:travel:import-geo {dump} — Import géographique legacy
 * (pays/villes, TRAVEL-1004, issue #6117).
 *
 * Upsert idempotent par clés naturelles (iso2 ; country_iso2+name),
 * contrôle qualité (ISO 3166-1 alpha-2, noms non vides, doublons),
 * rapport complet. `--dry-run` : analyse sans écriture.
 *
 * Usage :
 *   php artisan leopardo:travel:import-geo geo.json --tenant=<uuid>
 *   php artisan leopardo:travel:import-geo geo.json --tenant=<uuid> --dry-run
 */
class ImportLegacyGeoCommand extends Command
{
    protected $signature = 'leopardo:travel:import-geo {dump : chemin du fichier JSON géographique legacy}
        {--tenant= : id du tenant cible (obligatoire)}
        {--dry-run : analyse sans écriture}';

    protected $description = 'Importe le référentiel géographique legacy gv-back (pays/villes) — seed rejouable, rapport complet.';

    public function handle(TenantManager $tenants, LegacyGeoImportService $service): int
    {
        $path = (string) $this->argument('dump');
        $tenantId = (string) $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');

        if ($tenantId === '') {
            $this->error('--tenant est obligatoire.');

            return self::FAILURE;
        }

        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Dump introuvable : '.$path);

            return self::FAILURE;
        }

        try {
            $dump = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('JSON invalide : '.$e->getMessage());

            return self::FAILURE;
        }

        $company = Company::query()->find($tenantId);

        if (! $company instanceof Company) {
            $this->error('Tenant introuvable : '.$tenantId);

            return self::FAILURE;
        }

        $this->info(sprintf('Import géo legacy → %s (%s)%s', $company->name, $company->id, $dryRun ? ' [dry-run]' : ''));

        try {
            $report = $tenants->withinTenant($company, fn (): array => $service->import($company->id, $dump, $dryRun));
        } catch (Throwable $e) {
            $this->error('Échec de l\'import : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('  Pays : %d · Villes : %d', $report['countries'], $report['cities']));

        foreach ((array) ($report['skipped'] ?? []) as $skipped) {
            $this->warn('  ⚠ ignoré : '.$skipped);
        }

        $this->info('Rapport complet — seed géo rejouable.');

        return self::SUCCESS;
    }
}
