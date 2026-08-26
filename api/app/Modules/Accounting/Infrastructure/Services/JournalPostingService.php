<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Enums\PaymentStatus;
use App\Modules\Accounting\Domain\Exceptions\PeriodClosedException;
use App\Modules\Accounting\Domain\Exceptions\UnbalancedJournalEntryException;
use App\Modules\Accounting\Domain\Models\AccountingClosedPeriod;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Journal comptable — écritures débit/crédit dérivées des documents et des
 * paiements (issue #5234). Plan de comptes PCF/SYSCOHADA simplifié (Phase A,
 * non paramétrable) :
 *
 *   - 411  Clients                     (créances)
 *   - 70   Ventes de produits
 *   - 709  Rabais, remises et ristournes (avoirs)
 *   - 4457 TVA collectée
 *   - 512  Banques / 53 Caisse         (mouvement de trésorerie)
 *
 * Règles métier :
 *   - Seuls `invoice` et `credit_note` (statut ≠ draft/cancelled) produisent
 *     des écritures — proforma/devis/BL sont sans impact comptable, et le
 *     `receipt` est la preuve d'encaissement : c'est le PAIEMENT qui porte le
 *     mouvement de trésorerie (pas de double comptage).
 *   - Chaque posting est équilibré (Σ débit = Σ crédit) et idempotent
 *     (updateOrCreate sur la clé source+compte) : un re-posting rafraîchit
 *     les montants sans jamais dupliquer.
 *   - Une période clôturée est figée : tout posting daté dans une période
 *     close lève `PeriodClosedException`.
 */
final class JournalPostingService
{
    private const ACCOUNT_RECEIVABLE = ['411', 'Clients'];

    private const ACCOUNT_SALES = ['70', 'Ventes de produits'];

    private const ACCOUNT_DISCOUNT = ['709', 'Rabais, remises et ristournes'];

    private const ACCOUNT_VAT = ['4457', 'TVA collectée'];

    private const ACCOUNT_BANK = ['512', 'Banques'];

    private const ACCOUNT_CASH = ['53', 'Caisse'];

    private const TOLERANCE = 0.005;

    /** @var array<string, array{string, string}> */
    private const ACCOUNT_BY_METHOD = [
        'cash' => self::ACCOUNT_CASH,
        'bank_transfer' => self::ACCOUNT_BANK,
        'check' => self::ACCOUNT_BANK,
        'card' => self::ACCOUNT_BANK,
        // #5272 (ADR-0017) — paiement en ligne : trésorerie banque.
        'online_chargily' => self::ACCOUNT_BANK,
        'online_stripe' => self::ACCOUNT_BANK,
        'other' => self::ACCOUNT_BANK,
    ];

    /**
     * Passe (ou re-passe) un document comptable : écritures équilibrées,
     * idempotentes. Retourne le nombre d'écritures créées/mises à jour
     * (0 si le document n'a pas d'impact comptable).
     */
    public function postDocument(AccountingDocument $document): int
    {
        if (! in_array($document->type, [DocumentType::Invoice->value, DocumentType::CreditNote->value], true)) {
            return 0;
        }

        if (in_array($document->status, [DocumentStatus::Draft->value, DocumentStatus::Cancelled->value], true)) {
            return 0;
        }

        $this->guardPeriodOpen($document->issue_date);

        $lines = $document->type === DocumentType::Invoice->value
            ? $this->invoiceLines($document)
            : $this->creditNoteLines($document);

        $piece = (string) $document->number;
        $description = ($document->type === DocumentType::Invoice->value ? 'Facture' : 'Avoir').' '.$piece;

        return $this->postLines(
            sourceType: 'document',
            sourceId: $document->id,
            entryDate: $document->issue_date,
            piece: $piece,
            description: $description,
            lines: $lines,
        );
    }

    /**
     * Passe (ou re-passe) un paiement : mouvement de trésorerie
     * (512/53 ↔ 411). Les paiements `pending` (promesses) ne sont pas passés.
     */
    public function postPayment(AccountingPayment $payment): int
    {
        if ($payment->status === PaymentStatus::Pending->value || (float) $payment->amount <= 0.0) {
            return 0;
        }

        $entryDate = $payment->received_at ?? $payment->created_at;
        if (! $entryDate instanceof Carbon) {
            $entryDate = Carbon::now();
        }

        $this->guardPeriodOpen($entryDate);

        $method = PaymentMethod::tryFrom((string) $payment->method) ?? PaymentMethod::Other;
        $treasury = self::ACCOUNT_BY_METHOD[$method->value];

        $lines = [
            ['account' => $treasury[0], 'account_label' => $treasury[1], 'debit' => (float) $payment->amount, 'credit' => 0.0],
            ['account' => self::ACCOUNT_RECEIVABLE[0], 'account_label' => self::ACCOUNT_RECEIVABLE[1], 'debit' => 0.0, 'credit' => (float) $payment->amount],
        ];

        $piece = 'PAY-'.$payment->id;
        $description = 'Encaissement '.$method->value.($payment->reference !== null ? ' ('.$payment->reference.')' : '');

        return $this->postLines(
            sourceType: 'payment',
            sourceId: $payment->id,
            entryDate: $entryDate,
            piece: $piece,
            description: $description,
            lines: $lines,
        );
    }

    /**
     * Écritures du journal pour une période (YYYY-MM), ordonnées par date puis source.
     *
     * @return Collection<int, AccountingJournalEntry>
     */
    public function entriesForPeriod(string $period): Collection
    {
        return AccountingJournalEntry::query()
            ->where('period', $period)
            ->orderBy('entry_date')
            ->orderBy('source_id')
            ->orderBy('account_code')
            ->get();
    }

    /** @return array{debit: float, credit: float} */
    public function totalsForPeriod(string $period): array
    {
        $totals = AccountingJournalEntry::query()
            ->where('period', $period)
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        return [
            'debit' => round((float) ($totals->debit ?? 0), 2),
            'credit' => round((float) ($totals->credit ?? 0), 2),
        ];
    }

    public function isPeriodBalanced(string $period): bool
    {
        $totals = $this->totalsForPeriod($period);

        return abs($totals['debit'] - $totals['credit']) <= self::TOLERANCE;
    }

    /**
     * Clôture une période comptable (idempotent) : plus aucun posting accepté.
     */
    public function closePeriod(string $period, ?string $closedBy = null): AccountingClosedPeriod
    {
        return AccountingClosedPeriod::query()->firstOrCreate(
            ['period' => $period],
            ['closed_by' => $closedBy, 'closed_at' => Carbon::now()],
        );
    }

    public function isPeriodClosed(string $period): bool
    {
        return AccountingClosedPeriod::query()->where('period', $period)->exists();
    }

    /**
     * @return list<array{account: string, account_label: string, debit: float, credit: float}>
     */
    private function invoiceLines(AccountingDocument $document): array
    {
        return [
            ['account' => self::ACCOUNT_RECEIVABLE[0], 'account_label' => self::ACCOUNT_RECEIVABLE[1], 'debit' => (float) $document->total_ttc, 'credit' => 0.0],
            ['account' => self::ACCOUNT_SALES[0], 'account_label' => self::ACCOUNT_SALES[1], 'debit' => 0.0, 'credit' => (float) $document->subtotal_ht],
            ['account' => self::ACCOUNT_VAT[0], 'account_label' => self::ACCOUNT_VAT[1], 'debit' => 0.0, 'credit' => (float) $document->tax_amount],
        ];
    }

    /**
     * @return list<array{account: string, account_label: string, debit: float, credit: float}>
     */
    private function creditNoteLines(AccountingDocument $document): array
    {
        return [
            ['account' => self::ACCOUNT_DISCOUNT[0], 'account_label' => self::ACCOUNT_DISCOUNT[1], 'debit' => (float) $document->subtotal_ht, 'credit' => 0.0],
            ['account' => self::ACCOUNT_VAT[0], 'account_label' => self::ACCOUNT_VAT[1], 'debit' => (float) $document->tax_amount, 'credit' => 0.0],
            ['account' => self::ACCOUNT_RECEIVABLE[0], 'account_label' => self::ACCOUNT_RECEIVABLE[1], 'debit' => 0.0, 'credit' => (float) $document->total_ttc],
        ];
    }

    private function guardPeriodOpen(Carbon $entryDate): void
    {
        $period = $entryDate->format('Y-m');
        if ($this->isPeriodClosed($period)) {
            throw new PeriodClosedException($period);
        }
    }

    /**
     * @param  list<array{account: string, account_label: string, debit: float, credit: float}>  $lines
     */
    private function postLines(
        string $sourceType,
        int $sourceId,
        Carbon $entryDate,
        string $piece,
        string $description,
        array $lines,
    ): int {
        $debit = round((float) array_sum(array_column($lines, 'debit')), 2);
        $credit = round((float) array_sum(array_column($lines, 'credit')), 2);

        if (abs($debit - $credit) > self::TOLERANCE) {
            throw new UnbalancedJournalEntryException($debit, $credit, $sourceType.' #'.$sourceId);
        }

        $period = $entryDate->format('Y-m');

        return DB::transaction(function () use ($sourceType, $sourceId, $entryDate, $period, $piece, $description, $lines): int {
            $count = 0;

            foreach ($lines as $line) {
                AccountingJournalEntry::query()->updateOrCreate(
                    [
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                        'account_code' => $line['account'],
                    ],
                    [
                        'entry_date' => $entryDate,
                        'period' => $period,
                        'account_label' => $line['account_label'],
                        'debit' => round($line['debit'], 2),
                        'credit' => round($line['credit'], 2),
                        'piece' => $piece,
                        'description' => $description,
                    ],
                );
                $count++;
            }

            return $count;
        });
    }
}
