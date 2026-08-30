<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * billing:report — compteurs de supervision du billing (DEP-BC21 #6251).
 *
 * Agrège des compteurs NON nominatifs (aucune PII) : souscriptions par
 * statut, factures par statut, paiements par statut. À lancer à la demande
 * pendant une investigation (recouvrement, webhooks, réconciliation) — le
 * runbook docs/ops/RUNBOOK_BILLING.md s'y réfère.
 */
class BillingReportCommand extends Command
{
    protected $signature = 'billing:report';

    protected $description = 'Affiche les compteurs billing (souscriptions, factures, paiements) pour la supervision.';

    public function handle(): int
    {
        $subscriptions = Subscription::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $invoices = Invoice::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Table tenant, sans import cross-module (ADRs billing/payroll).
        $payments = DB::table('payments')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->newLine();
        $this->line('Souscriptions par statut :');
        $this->table(
            ['Statut', 'Count'],
            collect(SubscriptionStatus::cases())
                ->map(fn (SubscriptionStatus $status): array => [$status->value, (int) ($subscriptions[$status->value] ?? 0)])
                ->all(),
        );

        $this->line('Factures par statut :');
        $this->table(
            ['Statut', 'Count'],
            collect(InvoiceStatus::cases())
                ->map(fn (InvoiceStatus $status): array => [$status->value, (int) ($invoices[$status->value] ?? 0)])
                ->all(),
        );

        $this->line('Paiements par statut :');
        $this->table(
            ['Statut', 'Count'],
            [
                ['pending', (int) ($payments['pending'] ?? 0)],
                ['completed', (int) ($payments['completed'] ?? 0)],
                ['failed', (int) ($payments['failed'] ?? 0)],
                ['refunded', (int) ($payments['refunded'] ?? 0)],
            ],
        );

        return self::SUCCESS;
    }
}
