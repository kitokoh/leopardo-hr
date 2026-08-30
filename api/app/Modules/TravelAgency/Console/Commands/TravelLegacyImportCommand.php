<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Infrastructure\Services\TravelLegacyImportService;
use Illuminate\Console\Command;
use JsonException;

/**
 * leopardo:travel:import-legacy — Import contrôlé des données legacy
 * gv-back (TRAVEL-1003, #6116).
 *
 * Usage :
 *   php artisan leopardo:travel:import-legacy {dump} --company={id|slug} [--dry-run]
 *
 * Mapping documenté (spec §12, V5) : routes (par code, villes résolues par
 * nom), trajets (par code, statuts normalisés), tarifs (trip + classe),
 * réservations (par référence, statuts figés, passagers créés à la
 * création), contacts CRM (sans consentement — opt-in reste explicite).
 * Idempotent : rejouable sans doublon (updateOrCreate sur clés uniques
 * tenant-scoped) ; rapport complet par entité.
 */
final class TravelLegacyImportCommand extends Command
{
    protected $signature = 'leopardo:travel:import-legacy
        {dump : chemin du fichier JSON (dump gv-back)}
        {--company= : ID (UUID) ou slug du tenant cible (requis)}
        {--dry-run : calcule le rapport sans rien écrire}';

    protected $description = 'Import contrôlé du dump legacy gv-back (routes, trajets, tarifs, réservations, contacts).';

    public function handle(
        TenantManager $tenantManager,
        TravelLegacyImportService $service,
    ): int {
        $company = $this->resolveCompany((string) $this->option('company'));

        if ($company === null) {
            $this->error("Company introuvable : {$this->option('company')}");

            return self::FAILURE;
        }

        $path = (string) $this->argument('dump');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Fichier dump illisible : {$path}");

            return self::FAILURE;
        }

        try {
            $dump = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('JSON invalide : '.$e->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $report = $tenantManager->withinTenant(
            $company,
            fn (): array => $service->import($company, $dump, $dryRun),
        );

        $this->table(
            ['Entité', 'Créées', 'Mises à jour', 'Skippées'],
            [
                ['routes', $report['routes']['created'] ?? '-', $report['routes']['updated'] ?? '-', $report['routes']['skipped'] ?? '-'],
                ['trips', $report['trips']['created'] ?? '-', $report['trips']['updated'] ?? '-', $report['trips']['skipped'] ?? '-'],
                ['prices', $report['prices']['created'] ?? '-', $report['prices']['updated'] ?? '-', $report['prices']['skipped'] ?? '-'],
                ['bookings', $report['bookings']['created'] ?? '-', $report['bookings']['updated'] ?? '-', $report['bookings']['skipped'] ?? '-'],
                ['passengers', $report['passengers']['created'] ?? '-', '-', $report['passengers']['skipped'] ?? '-'],
                ['contacts', $report['contacts']['created'] ?? '-', $report['contacts']['updated'] ?? '-', $report['contacts']['skipped'] ?? '-'],
            ],
        );

        $this->info($dryRun
            ? 'Dry-run terminé — aucune écriture effectuée.'
            : 'Import terminé — rejouable sans doublon (clés uniques tenant-scoped).');

        return self::SUCCESS;
    }

    private function resolveCompany(string $identifier): ?Company
    {
        if (str_contains($identifier, '-')) {
            return Company::query()->where('id', $identifier)->first();
        }

        return Company::query()->where('slug', $identifier)->first();
    }
}
