<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Infrastructure\Services\TravelSalesSettlementService;
use Illuminate\Console\Command;

/**
 * TRAVEL-417 (#6069) — Synthèse Accounting des ventes confirmées.
 *
 * Pour chaque tenant actif et chaque période demandée, calcule la synthèse
 * des paiements confirmés/remboursés (minor units, devise) et publie
 * l'événement rejouable `travel.sales.settled.v1` — le BC Accounting
 * construit ses écritures depuis cet événement (jamais d'accès direct aux
 * tables Accounting depuis la verticale).
 *
 * Idempotent : la clé (période, devise) déduplique les rejeux — même
 * période = même montant (critère TRAVEL-417).
 *
 * Usage : php artisan travel:sales-settle --from=2026-09-01 --to=2026-09-30
 */
class TravelSalesSettleCommand extends Command
{
    protected $signature = 'travel:sales-settle
        {--company= : Cibler un tenant précis}
        {--from= : Borne basse (YYYY-MM-DD, défaut -7 j)}
        {--to= : Borne haute (YYYY-MM-DD, défaut aujourd\'hui)}';

    protected $description = 'Publie la synthèse Accounting des ventes travel confirmées (TRAVEL-417).';

    public function handle(TenantManager $tenantManager, TravelSalesSettlementService $service): int
    {
        $companies = Company::query()
            ->where('status', 'active')
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('No active company — nothing to settle.');

            return self::SUCCESS;
        }

        $from = $this->option('from') ? (string) $this->option('from') : now()->subDays(7)->toDateString();
        $to = $this->option('to') ? (string) $this->option('to') : now()->toDateString();

        $total = 0;

        foreach ($companies as $company) {
            $payload = $tenantManager->withinTenant(
                $company,
                fn (): ?array => $service->settle((string) $company->id, $from, $to),
            );

            if ($payload !== null) {
                $this->info(
                    "Tenant {$company->id}: synthèse {$payload['currency']} "
                    ."{$payload['confirmed_count']} paiement(s) confirmé(s) "
                    ."{$payload['refunded_count']} remboursé(s)."
                );
                $total++;
            }
        }

        $this->info("Total: {$total} synthèse(s) publiée(s).");

        return self::SUCCESS;
    }
}
