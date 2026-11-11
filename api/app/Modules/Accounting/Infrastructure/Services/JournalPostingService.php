<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
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
 * paiements (issue #5234, refonte #9367/#9368).
 *
 * Les comptes ne sont **plus codés en dur** : ils sont résolus par
 * {@see ChartAccountResolver}, qui applique la priorité
 * plan comptable du tenant → registre pays (PCG / SYSCOHADA / Tekdüzen /
 * UK / US / CA) → repli PCG. Un tenant turc poste donc sur `120`/`600`/`391`,
 * un tenant OHADA sur `411`/`706`/`44571`, un tenant PCG sur `411`/`706`/`4457`
 * — et le grand livre reste aligné sur le plan comptable provisionné.
 *
 * Schémas d'écriture :
 *   - Vente        : 411 Clients (D, TTC) / 7xx Ventes (C, HT) / 445x TVA collectée (C, taxe)
 *   - Avoir vente  : 709 Rabais (D, HT) + 445x TVA (D, taxe) / 411 Clients (C, TTC)
 *   - Achat        : 607 Achats (D, HT) + 445x TVA déductible (D, taxe) / 401 Fournisseurs (C, TTC)
 *   - Avoir achat  : 401 Fournisseurs (D, TTC) / 607 Achats (C, HT) + 445x TVA déductible (C, taxe)
 *   - Encaissement : 512 Banque ou 53 Caisse (D) / 411 Clients (C)
 *   - Décaissement : 401 Fournisseurs (D) / 512 Banque ou 53 Caisse (C)
 *
 * Règles métier :
 *   - Seuls les documents à impact comptable produisent des écritures
 *     (proforma / devis / BL n'en produisent pas ; un `receipt` est la preuve
 *     d'encaissement — c'est le PAIEMENT qui porte la trésorerie, pas de double
 *     comptage) ;
 *   - Chaque posting est équilibré (Σ débit = Σ crédit) et idempotent
 *     (updateOrCreate sur la clé source+compte) : un re-posting rafraîchit les
 *     montants sans jamais dupliquer ;
 *   - Une période clôturée est figée : tout posting daté dans une période close
 *     lève `PeriodClosedException`.
 */
final class JournalPostingService
{
    private const TOLERANCE = 0.005;

    /** Types de documents portant une écriture comptable. */
    public const POSTABLE_TYPES = [
        DocumentType::Invoice->value,
        DocumentType::CreditNote->value,
        DocumentType::PurchaseInvoice->value,
        DocumentType::PurchaseCreditNote->value,
    ];

    public function __construct(
        private readonly ?ChartAccountResolver $resolver = null,
        private readonly ?JournalResolver $journals = null,
    ) {}

    /**
     * Passe (ou re-passe) un document comptable : écritures équilibrées,
     * idempotentes. Retourne le nombre d'écritures créées/mises à jour
     * (0 si le document n'a pas d'impact comptable).
     */
    public function postDocument(AccountingDocument $document): int
    {
        if (! in_array($document->type, self::POSTABLE_TYPES, true)) {
            return 0;
        }

        if (in_array($document->status, [DocumentStatus::Draft->value, DocumentStatus::Cancelled->value], true)) {
            return 0;
        }

        $this->guardPeriodOpen($document->issue_date);

        $resolver = $this->resolverFor((string) $document->company_id);

        // `POSTABLE_TYPES` a déjà restreint `$document->type` : une chaîne de
        // `if` évite un `match` dont la dernière branche serait toujours vraie
        // (PHPStan level 8, `match.alwaysTrue`).
        if ($document->type === DocumentType::Invoice->value) {
            [$lines, $description] = [$this->salesInvoiceLines($document, $resolver), 'Facture'];
        } elseif ($document->type === DocumentType::CreditNote->value) {
            [$lines, $description] = [$this->salesCreditNoteLines($document, $resolver), 'Avoir'];
        } elseif ($document->type === DocumentType::PurchaseInvoice->value) {
            [$lines, $description] = [$this->purchaseInvoiceLines($document, $resolver), 'Facture fournisseur'];
        } else {
            [$lines, $description] = [$this->purchaseCreditNoteLines($document, $resolver), 'Avoir fournisseur'];
        }

        if ($lines === []) {
            return 0;
        }

        $piece = (string) $document->number;

        return $this->postLines(
            companyId: (string) $document->company_id,
            sourceType: 'document',
            sourceId: (int) $document->id,
            entryDate: $document->issue_date,
            piece: $piece,
            description: $description.' '.$piece,
            lines: $lines,
            journalCode: $this->journalCodeForDocument($document, $resolver),
        );
    }

    /**
     * Passe (ou re-passe) un paiement : mouvement de trésorerie.
     *
     * Un paiement rattaché à un document d'achat est un **décaissement**
     * (401 → trésorerie) ; sinon c'est un encaissement (trésorerie → 411).
     * Les paiements `pending` (promesses) ne sont pas passés.
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

        $resolver = $this->resolverFor((string) $payment->company_id);

        $method = PaymentMethod::tryFrom((string) $payment->method) ?? PaymentMethod::Other;
        $treasury = $resolver->entry($resolver->treasuryFamilyFor($method->value));

        $isDisbursement = $this->paymentTargetsPurchase($payment);
        $thirdParty = $resolver->entry($isDisbursement ? 'suppliers' : 'clients');

        $amount = (float) $payment->amount;

        $lines = $isDisbursement
            ? [
                ['account' => $thirdParty['code'], 'account_label' => $thirdParty['label'], 'debit' => $amount, 'credit' => 0.0],
                ['account' => $treasury['code'], 'account_label' => $treasury['label'], 'debit' => 0.0, 'credit' => $amount],
            ]
            : [
                ['account' => $treasury['code'], 'account_label' => $treasury['label'], 'debit' => $amount, 'credit' => 0.0],
                ['account' => $thirdParty['code'], 'account_label' => $thirdParty['label'], 'debit' => 0.0, 'credit' => $amount],
            ];

        $piece = 'PAY-'.$payment->id;
        $description = ($isDisbursement ? 'Décaissement ' : 'Encaissement ').$method->value
            .($payment->reference !== null ? ' ('.$payment->reference.')' : '');

        return $this->postLines(
            companyId: (string) $payment->company_id,
            sourceType: 'payment',
            sourceId: (int) $payment->id,
            entryDate: $entryDate,
            piece: $piece,
            description: $description,
            lines: $lines,
            journalCode: $this->journalCodeForPayment((string) $payment->company_id, $method, $resolver),
        );
    }

    /**
     * Écritures du journal pour une période (YYYY-MM), ordonnées par date puis source.
     *
     * @return Collection<int, AccountingJournalEntry>
     *
     * Issue #6562 — limit optionnel pour borner les réponses volumineuses.
     */
    public function entriesForPeriod(string $period, ?int $limit = null): Collection
    {
        $query = AccountingJournalEntry::query()
            ->where('period', $period)
            ->orderBy('entry_date')
            ->orderBy('source_id')
            ->orderBy('account_code');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
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
    public function closePeriod(string $period, ?string $closedBy = null, ?string $companyId = null): AccountingClosedPeriod
    {
        $attributes = ['period' => $period];

        if ($companyId !== null) {
            $attributes['company_id'] = $companyId;
        }

        return AccountingClosedPeriod::query()->firstOrCreate(
            $attributes,
            ['closed_by' => $closedBy, 'closed_at' => Carbon::now()],
        );
    }

    public function isPeriodClosed(string $period): bool
    {
        return AccountingClosedPeriod::query()->where('period', $period)->exists();
    }

    /**
     * Résolveur de comptes pour une entreprise — injection explicite si le
     * conteneur en fournit un (tests de résolution pure), sinon résolution par
     * le plan comptable du tenant puis le pays.
     */
    private function resolverFor(string $companyId): ChartAccountResolver
    {
        if ($this->resolver !== null) {
            return $this->resolver;
        }

        /** @var Company|null $company */
        $company = Company::query()->withoutGlobalScopes()->find($companyId);

        return $company !== null
            ? ChartAccountResolver::forCompany($company)
            : ChartAccountResolver::forCountry(null);
    }

    /**
     * @return list<array{account: string, account_label: string, debit: float, credit: float}>
     */
    private function salesInvoiceLines(AccountingDocument $document, ChartAccountResolver $resolver): array
    {
        $revenue = $resolver->entry('sales_revenue');
        $thirdParty = $resolver->entry('clients');
        $vat = $resolver->entry('vat_collected');

        $lines = [
            ['account' => $thirdParty['code'], 'account_label' => $thirdParty['label'], 'debit' => (float) $document->total_ttc, 'credit' => 0.0],
            ['account' => $revenue['code'], 'account_label' => $revenue['label'], 'debit' => 0.0, 'credit' => (float) $document->subtotal_ht],
        ];

        $tax = (float) $document->tax_amount;
        if ($tax > 0.0) {
            $lines[] = ['account' => $vat['code'], 'account_label' => $vat['label'], 'debit' => 0.0, 'credit' => $tax];
        }

        return $lines;
    }

    /**
     * Avoir de vente : rabais + TVA au débit, client au crédit.
     *
     * @return list<array{account: string, account_label: string, debit: float, credit: float}>
     */
    private function salesCreditNoteLines(AccountingDocument $document, ChartAccountResolver $resolver): array
    {
        $discount = $resolver->entry('sales_discount');
        $thirdParty = $resolver->entry('clients');
        $vat = $resolver->entry('vat_collected');

        $lines = [
            ['account' => $discount['code'], 'account_label' => $discount['label'], 'debit' => (float) $document->subtotal_ht, 'credit' => 0.0],
        ];

        $tax = (float) $document->tax_amount;
        if ($tax > 0.0) {
            $lines[] = ['account' => $vat['code'], 'account_label' => $vat['label'], 'debit' => $tax, 'credit' => 0.0];
        }

        $lines[] = ['account' => $thirdParty['code'], 'account_label' => $thirdParty['label'], 'debit' => 0.0, 'credit' => (float) $document->total_ttc];

        return $lines;
    }

    /**
     * Facture d'achat : charge + TVA déductible au débit, fournisseur au crédit.
     *
     * @return list<array{account: string, account_label: string, debit: float, credit: float}>
     */
    private function purchaseInvoiceLines(AccountingDocument $document, ChartAccountResolver $resolver): array
    {
        $charge = $resolver->entry('purchases');
        $thirdParty = $resolver->entry('suppliers');
        $vat = $resolver->entry('vat_deductible');

        $lines = [
            ['account' => $charge['code'], 'account_label' => $charge['label'], 'debit' => (float) $document->subtotal_ht, 'credit' => 0.0],
        ];

        $tax = (float) $document->tax_amount;
        if ($tax > 0.0) {
            $lines[] = ['account' => $vat['code'], 'account_label' => $vat['label'], 'debit' => $tax, 'credit' => 0.0];
        }

        $lines[] = ['account' => $thirdParty['code'], 'account_label' => $thirdParty['label'], 'debit' => 0.0, 'credit' => (float) $document->total_ttc];

        return $lines;
    }

    /**
     * Avoir fournisseur : fournisseur au débit, charge + TVA déductible au crédit.
     *
     * @return list<array{account: string, account_label: string, debit: float, credit: float}>
     */
    private function purchaseCreditNoteLines(AccountingDocument $document, ChartAccountResolver $resolver): array
    {
        $charge = $resolver->entry('purchase_discount');
        $thirdParty = $resolver->entry('suppliers');
        $vat = $resolver->entry('vat_deductible');

        $lines = [
            ['account' => $thirdParty['code'], 'account_label' => $thirdParty['label'], 'debit' => (float) $document->total_ttc, 'credit' => 0.0],
            ['account' => $charge['code'], 'account_label' => $charge['label'], 'debit' => 0.0, 'credit' => (float) $document->subtotal_ht],
        ];

        $tax = (float) $document->tax_amount;
        if ($tax > 0.0) {
            $lines[] = ['account' => $vat['code'], 'account_label' => $vat['label'], 'debit' => 0.0, 'credit' => $tax];
        }

        return $lines;
    }

    /**
     * Un paiement porte-t-il sur un document d'achat (= décaissement) ?
     */
    private function paymentTargetsPurchase(AccountingPayment $payment): bool
    {
        $documentId = $payment->document_id ?? null;

        if ($documentId === null) {
            return false;
        }

        return AccountingDocument::query()
            ->withoutGlobalScopes()
            ->whereKey($documentId)
            ->whereIn('type', [DocumentType::PurchaseInvoice->value, DocumentType::PurchaseCreditNote->value])
            ->exists();
    }

    /**
     * Journal d'un document : résolu par le type de document (vente → journal
     * des ventes, achat → journal des achats), plus par le préfixe du compte.
     */
    private function journalCodeForDocument(AccountingDocument $document, ChartAccountResolver $resolver): string
    {
        $type = DocumentType::tryFrom((string) $document->type) ?? DocumentType::Invoice;

        return $this->journalResolver()->forDocumentType(
            $this->journalsFor((string) $document->company_id, $resolver),
            $type,
        );
    }

    /**
     * Journal d'un paiement : journal de caisse pour les espèces, journal de
     * banque sinon.
     */
    private function journalCodeForPayment(string $companyId, PaymentMethod $method, ChartAccountResolver $resolver): string
    {
        return $this->journalResolver()->forPaymentMethod(
            $this->journalsFor($companyId, $resolver),
            $method,
        );
    }

    /**
     * Journaux actifs du tenant, provisionnés à la volée si l'entreprise a été
     * créée avant l'introduction de l'entité journal (#9370). Évite un script
     * de migration de données et garantit qu'un posting ne peut pas échouer
     * faute de journal.
     *
     * @return list<\App\Modules\Accounting\Domain\Models\AccountingJournal>
     */
    private function journalsFor(string $companyId, ChartAccountResolver $resolver): array
    {
        $journals = $this->journalResolver()->journalsFor($companyId);

        if ($journals !== []) {
            return $journals;
        }

        $this->journalResolver()->seedDefaults($companyId, $resolver->country(), $resolver);

        return $this->journalResolver()->journalsFor($companyId);
    }

    private function journalResolver(): JournalResolver
    {
        return $this->journals ?? new JournalResolver;
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
        string $companyId,
        string $sourceType,
        int $sourceId,
        Carbon $entryDate,
        string $piece,
        string $description,
        array $lines,
        string $journalCode = 'OD',
    ): int {
        $debit = round((float) array_sum(array_column($lines, 'debit')), 2);
        $credit = round((float) array_sum(array_column($lines, 'credit')), 2);

        if (abs($debit - $credit) > self::TOLERANCE) {
            throw new UnbalancedJournalEntryException($debit, $credit, $sourceType.' #'.$sourceId);
        }

        $period = $entryDate->format('Y-m');

        return DB::transaction(function () use ($companyId, $sourceType, $sourceId, $entryDate, $period, $piece, $description, $lines, $journalCode): int {
            $count = 0;

            foreach ($lines as $line) {
                AccountingJournalEntry::query()->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                        'account_code' => $line['account'],
                        'line_no' => 1,
                    ],
                    [
                        'entry_date' => $entryDate,
                        'period' => $period,
                        'journal_code' => $journalCode,
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
