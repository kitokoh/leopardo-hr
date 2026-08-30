<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Payroll\Domain\Models\Payment;
use Illuminate\Console\Command;

/**
 * billing:report — supervision du recouvrement (DEP-BC21, issue #6251).
 *
 * Agrégats console cross-tenant (jamais exposés en surface API) : comptes
 * par statut de souscription, factures overdue/paid, paiements récents.
 * Sortie structurée, zéro bruit si aucun écart (les lignes vides sont
 * omises). Usage en supervision (cron/scheduler + alerting sur les valeurs).
 *
 * Usage :
 *   php artisan billing:report
 *   php artisan billing:report --json
 */
class BillingReportCommand extends Command
{
    protected $signature = 'billing:report
        {--json : sortie JSON structurée (supervision/alerting)}';

    protected $description = 'Rapport de supervision billing (DEP-BC21) : souscriptions, factures, paiements récents.';

    public function handle(): int
    {
        $subscriptionsByStatus = Subscription::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $invoicesOverdue = Invoice::query()->where('status', 'overdue')->count();
        $invoicesPaid = Invoice::query()->where('status', 'paid')->count();
        $invoicesPending = Invoice::query()->where('status', 'pending')->count();

        $paymentsRecent = Payment::query()
            ->where('paid_at', '>=', now()->subDays(7))
            ->count();

        $report = [
            'subscriptions' => $subscriptionsByStatus,
            'invoices' => [
                'overdue' => $invoicesOverdue,
                'paid' => $invoicesPaid,
                'pending' => $invoicesPending,
            ],
            'payments_last_7d' => $paymentsRecent,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('[billing:report]');

        if ($subscriptionsByStatus !== []) {
            $this->table(['Statut', 'Souscriptions'], collect($subscriptionsByStatus)
                ->map(fn (int $total, string $status): array => [$status, (string) $total])
                ->values()
                ->all());
        }

        $this->line(sprintf(
            '  Invoices : %d overdue, %d paid, %d pending — paiements 7j : %d',
            $invoicesOverdue,
            $invoicesPaid,
            $invoicesPending,
            $paymentsRecent
        ));

        if ($invoicesOverdue > 0) {
            $this->warn(sprintf('  ⚠ %d facture(s) en retard — voir RUNBOOK_BILLING.md (recouvrement).', $invoicesOverdue));
        }

        return self::SUCCESS;
    }
}
