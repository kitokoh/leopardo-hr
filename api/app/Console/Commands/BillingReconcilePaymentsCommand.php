<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * billing:reconcile-payments — réconciliation paiements ↔ factures (DEP-BC21 #6249).
 *
 * Les webhooks providers (Stripe/Chargily) créent des paiements et marquent
 * des factures paid de façon dispersée ; une panne webhook, un retry ou un
 * changement de provider laisse des écarts. Cette commande signale (dry-run
 * par défaut) puis corrige (--apply) UNIQUEMENT les écarts sûrs :
 *
 *   1. paiement `completed` dont la facture n'est pas `paid` (montant et
 *      company concordants) → la facture est marquée paid via la machine à
 *      états (transition `sent/pending/overdue → paid`) ;
 *   2. doublons de `provider_reference` → signalés (jamais corrigés) ;
 *   3. facture `paid` sans paiement `completed` → signalée (jamais corrigée,
 *      aucune création de paiement fantôme).
 *
 * Code retour : 0 = aucun écart, 2 = écarts signalés. Idempotente : rejouer
 * --apply sur un état cohérent ne change plus rien.
 *
 * Usage :
 *   php artisan billing:reconcile-payments              # dry-run
 *   php artisan billing:reconcile-payments --apply      # corrige les écarts sûrs
 */
class BillingReconcilePaymentsCommand extends Command
{
    protected $signature = 'billing:reconcile-payments
        {--apply : applique les corrections sûres (défaut : dry-run)}';

    protected $description = 'Réconcilie les paiements avec les factures (écarts, doublons, montants) — dry-run par défaut.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $issues = [];

        // ── 1. Doublons de provider_reference ───────────────────────────────
        $duplicates = DB::table('payments')
            ->whereNotNull('provider_reference')
            ->where('provider_reference', '!=', '')
            ->groupBy('provider_reference')
            ->having(DB::raw('count(*)'), '>', 1)
            ->pluck('provider_reference');

        foreach ($duplicates as $reference) {
            $issues[] = "doublon provider_reference: {$reference}";
        }

        // ── 2. Paiements completed → facture non payée ───────────────────────
        $completedPayments = DB::table('payments')
            ->where('status', 'completed')
            ->whereNotNull('invoice_id')
            ->get(['id', 'invoice_id', 'company_id', 'amount']);

        foreach ($completedPayments as $payment) {
            $invoice = Invoice::query()->find((int) $payment->invoice_id);

            if (! $invoice) {
                $issues[] = "payment #{$payment->id} → facture #{$payment->invoice_id} introuvable";

                continue;
            }

            if ($invoice->company_id !== $payment->company_id) {
                $issues[] = "payment #{$payment->id} → facture #{$invoice->id} : company différente (isolation)";

                continue;
            }

            if ($invoice->status === InvoiceStatus::Paid->value) {
                continue; // déjà cohérent
            }

            $amountMatches = abs((float) $payment->amount - (float) $invoice->total) < 0.01;

            if (! $amountMatches) {
                $issues[] = "payment #{$payment->id} → facture #{$invoice->id} : montant incohérent (payé {$payment->amount}, facture {$invoice->total})";

                continue;
            }

            // Écart SÛR : montant + company concordants. Correction via la
            // machine à états (transition gardée — draft refusé = signalé).
            try {
                if ($apply) {
                    $invoice->transitionTo(InvoiceStatus::Paid, [
                        'paid_at' => now(),
                        'payment_method' => $invoice->payment_method ?? 'manual',
                    ]);
                    Log::info('billing:reconcile-payments: facture marquée payée', [
                        'invoice_id' => $invoice->id,
                        'company_id' => $invoice->company_id,
                    ]);
                } else {
                    $issues[] = "payment #{$payment->id} → facture #{$invoice->id} non payée (corrigeable avec --apply)";
                }
            } catch (\InvalidArgumentException $e) {
                $issues[] = "payment #{$payment->id} → facture #{$invoice->id} : transition refusée ({$invoice->status} → paid)";
            }
        }

        // ── 3. Factures paid sans paiement completed ─────────────────────────
        $paidInvoices = Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->get(['id', 'company_id']);

        foreach ($paidInvoices as $invoice) {
            $hasCompletedPayment = DB::table('payments')
                ->where('invoice_id', $invoice->id)
                ->where('status', 'completed')
                ->exists();

            if (! $hasCompletedPayment) {
                $issues[] = "facture #{$invoice->id} paid sans paiement completed (vérification manuelle)";
            }
        }

        // ── Rapport ──────────────────────────────────────────────────────────
        if ($issues === []) {
            $this->info('[billing:reconcile-payments] Aucun écart détecté.');

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->warn("  - {$issue}");
        }
        $this->warn(sprintf(
            '[billing:reconcile-payments] %d écart(s) %s.',
            count($issues),
            $apply ? 'corrigés' : 'signalés (dry-run — relancer avec --apply pour corriger les écarts sûrs)',
        ));

        return 2;
    }
}
