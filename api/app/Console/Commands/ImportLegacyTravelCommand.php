<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Infrastructure\Services\LegacyTravelImportService;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

/**
 * leopardo:travel:import-legacy {dump} — Import contrôlé des données
 * legacy gv-back (TRAVEL-1003, issue #6116).
 *
 * Format JSON documenté (voir `docs/architecture/travel-legacy-import.md`).
 * `--dry-run` : analyse + rapport SANS aucune écriture. Idempotent : un
 * rejeu ne produit aucun doublon (clés externes + `legacy:{id}`).
 *
 * Usage :
 *   php artisan leopardo:travel:import-legacy dump.json --tenant=<uuid>
 *   php artisan leopardo:travel:import-legacy dump.json --tenant=<uuid> --dry-run
 */
class ImportLegacyTravelCommand extends Command
{
    protected $signature = 'leopardo:travel:import-legacy {dump : chemin du fichier JSON legacy}
        {--tenant= : id du tenant cible (obligatoire)}
        {--dry-run : analyse sans écriture}';

    protected $description = 'Importe les données legacy gv-back (compagnies, routes, trajets, tarifs, réservations) — idempotent, rapport complet.';

    public function handle(TenantManager $tenants, LegacyTravelImportService $service): int
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

        $this->info(sprintf('Import legacy gv-back → %s (%s)%s', $company->name, $company->id, $dryRun ? ' [dry-run]' : ''));

        try {
            $report = $tenants->withinTenant($company, fn (): array => $service->import($company->id, $dump, $dryRun));
        } catch (Throwable $e) {
            $this->error('Échec de l\'import : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            '  Compagnies : %d · Routes : %d · Trajets : %d · Tarifs : %d · Réservations : %d',
            $report['carriers'],
            $report['routes'],
            $report['trips'],
            $report['prices'],
            $report['bookings'],
        ));

        foreach ((array) ($report['skipped'] ?? []) as $skipped) {
            $this->warn('  ⚠ ignoré : '.$skipped);
        }

        $this->info('Rapport complet — import terminé.');

        return self::SUCCESS;
    }
}
