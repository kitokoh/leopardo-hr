<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Payroll\Domain\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * billing:reconcile-payments — réconciliation idempotente paiements ↔
 * factures (DEP-BC21, issue #6249).
 *
 * Les webhooks providers (Stripe/Chargily) créent les `payments` et marquent
 * les `invoices` paid de façon dispersée : une panne webhook, un retry ou un
 * changement de provider laisse des écarts (facture sent/overdue avec
 * paiement completed, doublons de référence, montants incohérents).
 *
 * Règles (dry-run par défaut, `--apply` pour corriger) :
 *  1. paiement `completed` dont la facture n'est pas `paid` → si montant et
 *     devise correspondent : marque la facture `paid` (idempotent, `--apply`
 *     rejoué ne change plus rien) ; sinon : écart signalé (jamais de
 *     correction aveugle) ;
 *  2. facture `paid` sans paiement `completed` → écart signalé (aucun
 *     paiement fantôme créé) ;
 *  3. doublons `provider_reference` → écart signalé.
 *
 * Logs structurés redacted (company_id + invoice_id, jamais de références
 * provider complètes, aucune PII). Code retour : 0 = aucun écart, 2 =
 * écarts signalés (utilisable en supervision).
 *
 * Usage :
 *   php artisan billing:reconcile-payments            # dry-run (défaut)
 *   php artisan billing:reconcile-payments --apply    # corrige les écarts sûrs
 */
class ReconcileBillingPaymentsCommand extends Command
{
    protected $signature = 'billing:reconcile-payments
        {--apply : applique les corrections sûres (défaut : dry-run)}';

    protected $description = 'Réconciliation idempotente paiements ↔ factures (DEP-BC21) — dry-run par défaut, --apply pour corriger.';

    /** @var list<string> */
    private array $issues = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $mode = $apply ? 'APPLY' : 'DRY-RUN';

        $this->reconcileCompletedPayments($apply);
        $this->detectPaidInvoicesWithoutPayment();
        $this->detectDuplicateReferences();

        $this->info(sprintf(
            '[billing:reconcile-payments] %s — %d écart(s) signalé(s).',
            $mode,
            count($this->issues)
        ));

        foreach ($this->issues as $issue) {
            $this->line('  - '.$issue);
        }

        if ($this->issues !== []) {
            Log::warning('[billing:reconcile-payments] écarts signalés', [
                'mode' => $mode,
                'count' => count($this->issues),
            ]);
        }

        return $this->issues === [] ? self::SUCCESS : 2;
    }

    /**
     * Règle 1 : paiement `completed` dont la facture n'est pas `paid`.
     */
    private function reconcileCompletedPayments(bool $apply): void
    {
        $payments = Payment::query()
            ->where('status', 'completed')
            ->with('invoice')
            ->get();

        foreach ($payments as $payment) {
            $invoice = $payment->invoice;

            if (! $invoice instanceof Invoice) {
                // Paiement sans facture liée : écart signalé (jamais de
                // rattachement automatique — la cible est ambiguë).
                $this->issues[] = sprintf(
                    'payment_orphan invoice=null payment_id=%d',
                    $payment->id
                );

                continue;
            }

            if ($invoice->status === 'paid') {
                continue;
            }

            $paymentAmount = (string) $payment->amount;
            $invoiceTotal = (string) $invoice->total;

            if (! is_numeric($paymentAmount) || ! is_numeric($invoiceTotal)) {
                continue;
            }

            $amountMatches = bccomp($paymentAmount, $invoiceTotal, 2) === 0;
            $currencyMatches = $payment->currency === $invoice->currency;

            if ($amountMatches && $currencyMatches) {
                if ($apply) {
                    $invoice->update([
                        'status' => 'paid',
                        'paid_at' => $payment->paid_at ?? now(),
                    ]);
                    Log::info('[billing:reconcile-payments] facture marquée paid', [
                        'company_id' => $invoice->company_id,
                        'invoice_id' => $invoice->id,
                    ]);
                } else {
                    $this->issues[] = sprintf(
                        'payment_completed_invoice_not_paid invoice=%d (correction sûre dispo avec --apply)',
                        $invoice->id
                    );
                }
            } else {
                $this->issues[] = sprintf(
                    'amount_mismatch invoice=%d payment=%d',
                    $invoice->id,
                    $payment->id
                );
            }
        }
    }

    /**
     * Règle 2 : facture `paid` sans paiement `completed`.
     */
    private function detectPaidInvoicesWithoutPayment(): void
    {
        $invoices = Invoice::query()
            ->where('status', 'paid')
            ->whereDoesntHave('payments', fn ($query) => $query->where('status', 'completed'))
            ->get();

        foreach ($invoices as $invoice) {
            $this->issues[] = sprintf(
                'invoice_paid_without_payment invoice=%d',
                $invoice->id
            );
        }
    }

    /**
     * Règle 3 : doublons de `provider_reference`.
     */
    private function detectDuplicateReferences(): void
    {
        $duplicates = Payment::query()
            ->whereNotNull('provider_reference')
            ->selectRaw('provider_reference, COUNT(*) as occurrences')
            ->groupBy('provider_reference')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            // Référence tronquée dans les logs (redaction, aucune PII).
            $ref = (string) $duplicate->provider_reference;
            $redacted = strlen($ref) > 12 ? substr($ref, 0, 6).'…'.substr($ref, -4) : '***';

            $this->issues[] = sprintf(
                'duplicate_provider_reference ref=%s occurrences=%d',
                $redacted,
                (int) $duplicate->occurrences
            );
        }
    }
}
