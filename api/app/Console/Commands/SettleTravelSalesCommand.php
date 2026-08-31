<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Infrastructure\Services\TravelSalesSettlementService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * leopardo:travel:settle-sales — Synthèse périodique des ventes TravelAgency
 * pour Accounting (TRAVEL-417, issue #6069).
 *
 * Agrège les paiements confirmés/remboursés de la période (défaut : hier)
 * pour CHAQUE tenant ayant de l'activité travel, publie
 * `travel.sales.settled.v1` via l'outbox. Idempotent : rejouer la même
 * période produit les mêmes montants (contrainte unique tenant/période/
 * devise). `--dry-run` affiche les agrégats sans rien écrire.
 *
 * Usage : php artisan leopardo:travel:settle-sales [--period=2026-08-29] [--dry-run]
 */
class SettleTravelSalesCommand extends Command
{
    protected $signature = 'leopardo:travel:settle-sales
        {--period= : période à synthétiser (YYYY-MM-DD, défaut : hier)}
        {--dry-run : affiche les agrégats sans écrire ni publier}';

    protected $description = 'Synthèse des ventes TravelAgency (paiements confirmés/remboursés) pour Accounting — idempotent par période.';

    public function handle(TenantManager $tenants, TravelSalesSettlementService $service): int
    {
        $period = $this->option('period');
        $day = $period !== null
            ? CarbonImmutable::parse((string) $period)
            : CarbonImmutable::now()->subDay();

        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Synthèse TravelAgency — période %s → %s%s',
            $day->toDateString(),
            $day->toDateString(),
            $dryRun ? ' (dry-run)' : '',
        ));

        $companies = Company::query()
            ->whereNotNull('schema_name')
            ->whereJsonContains('features', ['travelagency' => true])
            ->get();

        $total = 0;

        foreach ($companies as $company) {
            try {
                $tenants->withinTenant($company, function () use ($company, $service, $day, $dryRun, &$total): void {
                    $result = $service->settle($company->id, $day, $day, $dryRun);

                    if (! $dryRun) {
                        $total++;
                    }

                    $this->line(sprintf(
                        '  • %s (%s) : %d paiements confirmés (%d), %d remboursements (%d), net %d — %s',
                        $company->name,
                        $company->id,
                        $result['settlement']->confirmed_payments_count,
                        $result['settlement']->confirmed_amount_minor,
                        $result['settlement']->refunded_count,
                        $result['settlement']->refunded_amount_minor,
                        $result['settlement']->net_amount_minor,
                        $result['replayed'] ? 'rejoué' : ($dryRun ? 'aperçu' : 'écrit'),
                    ));
                });
            } catch (Throwable $e) {
                $this->error(sprintf('  • %s : %s', $company->id, $e->getMessage()));
            }
        }

        $this->info(sprintf('Terminé — %d synthèse(s) écrite(s).', $total));

        return self::SUCCESS;
    }
}
