<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Console\Command;

/**
 * TRAVEL-417 (#6069) — Synthèse ventes pour Accounting.
 *
 * Agrège les paiements confirmés/remboursés de la période (mois) et publie
 * l'événement `travel.sales.settled.v1` (période, montants minor units,
 * devise, compteurs) via l'outbox. Le BC Accounting (BC-08) consomme la
 * synthèse pour construire ses écritures — jamais d'écriture dans les
 * tables Accounting depuis la verticale (spec §8.5).
 *
 * Rejouable & idempotent : même période + mêmes montants → même clé
 * d'idempotence (hash événement+payload) → aucun doublon à la reprise.
 * Une nouvelle écriture sur une période déjà réglée produit une synthèse
 * à jour (mêmes totaux ou totaux amendés) — cohérence par période.
 */
class TravelSettleSalesCommand extends Command
{
    protected $signature = 'travel:settle-sales
        {--company= : Cibler un tenant précis}
        {--period= : Période YYYY-MM (défaut : mois précédent)}
        {--dry-run : Affiche la synthèse sans publier l\'événement}';

    protected $description = 'Publie la synthèse ventes (travel.sales.settled.v1) pour Accounting (TRAVEL-417/#6069).';

    public function __construct(private readonly TravelOutboxPublisher $outbox)
    {
        parent::__construct();
    }

    public function handle(TenantManager $tenantManager): int
    {
        $companies = Company::query()
            ->where('status', 'active')
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('Aucun tenant actif — rien à régler.');

            return self::SUCCESS;
        }

        $period = (string) ($this->option('period') ?? now()->subMonth()->format('Y-m'));
        $dryRun = (bool) $this->option('dry-run');
        $published = 0;

        foreach ($companies as $company) {
            $tenantPublished = $tenantManager->withinTenant(
                $company,
                fn (): int => $this->settleTenant((string) $company->id, $period, $dryRun),
            );

            $this->info("Tenant {$company->id} ({$period}) : {$tenantPublished} synthèse(s) publiée(s).");
            $published += $tenantPublished;
        }

        $this->info("Total : {$published} événement(s) travel.sales.settled.v1.");

        return self::SUCCESS;
    }

    private function settleTenant(string $companyId, string $period, bool $dryRun): int
    {
        [$year, $month] = array_map('intval', explode('-', $period));
        $from = now()->setDate($year, $month, 1)->startOfDay();
        $to = (clone $from)->addMonth()->startOfDay();

        $rows = TravelPayment::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [PaymentStatus::CONFIRMED->value, PaymentStatus::REFUNDED->value])
            ->whereBetween('created_at', [$from, $to])
            ->get(['status', 'amount_minor', 'currency']);

        // Agrégation par devise (un tenant peut encaisser en plusieurs devises).
        $byCurrency = [];

        foreach ($rows as $row) {
            $currency = (string) $row->currency;
            $byCurrency[$currency] = $byCurrency[$currency] ?? [
                'confirmed_total_minor' => 0,
                'refunded_total_minor' => 0,
                'confirmed_count' => 0,
                'refunded_count' => 0,
            ];

            if ($row->status === PaymentStatus::CONFIRMED->value) {
                $byCurrency[$currency]['confirmed_total_minor'] += (int) $row->amount_minor;
                $byCurrency[$currency]['confirmed_count']++;
            } else {
                $byCurrency[$currency]['refunded_total_minor'] += (int) $row->amount_minor;
                $byCurrency[$currency]['refunded_count']++;
            }
        }

        if ($byCurrency === []) {
            return 0;
        }

        $count = 0;

        foreach ($byCurrency as $currency => $totals) {
            $payload = array_merge([
                'period' => $period,
                'currency' => $currency,
                'settled_at' => now()->toIso8601String(),
            ], $totals);

            $this->line(sprintf(
                '[%s] %s — confirmé %d (%s), remboursé %d (%s)',
                $period,
                $currency,
                $totals['confirmed_count'],
                number_format($totals['confirmed_total_minor']),
                $totals['refunded_count'],
                number_format($totals['refunded_total_minor']),
            ));

            if (! $dryRun) {
                $this->outbox->publish($companyId, 'travel.sales.settled.v1', $payload);
                $count++;
            }
        }

        return $count;
    }
}
