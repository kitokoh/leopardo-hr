<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Services;

use App\Modules\Accounting\Domain\Contracts\DocumentNumberingInterface;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Enums\PaymentStatus;
use App\Modules\Accounting\Domain\Exceptions\CreditNoteRequiresSourceInvoiceException;
use App\Modules\Accounting\Domain\Exceptions\DeliveryNoteRequiresDeliveryDateException;
use App\Modules\Accounting\Domain\Exceptions\DocumentNotFullyPaidException;
use App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException;
use App\Modules\Accounting\Domain\Exceptions\InvalidDocumentTransitionException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Infrastructure\Services\TaxRateService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * #5223 — Cycle de vie des documents comptables (Comptabilité Phase A).
 *
 * Workflow : draft → sent → partially_paid → paid | cancelled (+ overdue
 * calculé). Règles de transition strictes :
 *   - send exige des lignes (+ contact pour facture/avoir) ;
 *   - `paid` n'est JAMAIS atteint sans paiement enregistré (seul
 *     recordPayment() fait la transition) ;
 *   - un document payé ne peut être ni annulé ni re-payé ;
 *   - un avoir est borné au reste à payer de sa facture source ;
 *   - la numérotation est attribuée à la création et retente sur 23505
 *     (contrainte unique (company_id, number), pattern upsert #4978).
 */
class DocumentWorkflowService
{
    public const MAX_NUMBERING_ATTEMPTS = 5;

    /**
     * Matrice des transitions autorisées (fusion #6572 : l'implémentation
     * Infrastructure dédiée a été supprimée — cette classe est désormais la
     * source unique du workflow documentaire #5223).
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        DocumentStatus::Draft->value => [DocumentStatus::Sent->value, DocumentStatus::Cancelled->value],
        DocumentStatus::Sent->value => [
            DocumentStatus::PartiallyPaid->value,
            DocumentStatus::Paid->value,
            DocumentStatus::Cancelled->value,
            DocumentStatus::Overdue->value,
        ],
        DocumentStatus::PartiallyPaid->value => [DocumentStatus::Paid->value, DocumentStatus::Overdue->value],
        DocumentStatus::Overdue->value => [DocumentStatus::Paid->value, DocumentStatus::PartiallyPaid->value],
        DocumentStatus::Paid->value => [],
        DocumentStatus::Cancelled->value => [],
    ];

    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        // Optionnel : les tests qui instancient le service à la main (avec un
        // numbering bouchonné) n'ont pas à connaître le référentiel de TVA.
        // Résolu par le container en production.
        private readonly ?TaxRateService $taxRates = null,
    ) {}

    /**
     * Référentiel de TVA, résolu paresseusement quand le service a été construit
     * sans lui (tests unitaires du workflow de numérotation).
     */
    private function taxRates(): TaxRateService
    {
        return $this->taxRates ?? app(TaxRateService::class);
    }

    /**
     * Taux d'une ligne (#9376) : `tax_rate` explicite > `tax_rate_code` résolu
     * dans le référentiel > taux du document > 0.
     *
     * Un code inconnu lève plutôt que de retomber silencieusement sur 0 : une
     * ligne à 0 % par erreur de saisie est une facture fausse.
     *
     * @param  array<string, mixed>  $line
     * @param  array<string, float>  $taxRates
     */
    private function resolveLineRate(array $line, ?float $documentRate, array $taxRates): float
    {
        if (isset($line['tax_rate']) && $line['tax_rate'] !== '') {
            return (float) $line['tax_rate'];
        }

        $code = $line['tax_rate_code'] ?? null;
        if (is_string($code) && $code !== '') {
            if (! array_key_exists($code, $taxRates)) {
                throw new DocumentWorkflowException(__('accounting.errors.wf_unknown_tax_rate', ['code' => $code]));
            }

            return (float) $taxRates[$code];
        }

        return $documentRate ?? 0.0;
    }

    /**
     * Identifiant du taux référencé par la ligne, s'il est connu du référentiel.
     *
     * @param  array<string, mixed>  $line
     * @param  array<string, int>  $taxRateIds
     */
    private function resolveLineRateId(array $line, array $taxRateIds): ?int
    {
        $code = $line['tax_rate_code'] ?? null;

        return is_string($code) && isset($taxRateIds[$code]) ? $taxRateIds[$code] : null;
    }

    /**
     * Transition générique de la machine à états (issue #5223).
     *
     * #6572 : méthode fusionnée depuis l'ancien
     * `Accounting\Infrastructure\Services\DocumentWorkflowService` (utilisée
     * par le seed démo et les tests) — la classe Infrastructure a été
     * supprimée ; cette classe est la source unique.
     */
    public function transition(AccountingDocument $document, DocumentStatus $to): AccountingDocument
    {
        $current = DocumentStatus::tryFrom($document->status) ?? DocumentStatus::Draft;

        $allowed = self::TRANSITIONS[$current->value];

        if (! in_array($to->value, $allowed, true)) {
            throw new InvalidDocumentTransitionException($current->value, $to->value, $allowed);
        }

        $this->assertTransitionBusinessRules($document, $current, $to);

        $document->update(['status' => $to->value]);

        return $document->refresh();
    }

    /**
     * Lie un avoir à sa facture source (même entreprise).
     *
     * #6572 : méthode fusionnée depuis l'ancien
     * `Accounting\Infrastructure\Services\DocumentWorkflowService`.
     */
    public function linkCreditNote(AccountingDocument $creditNote, AccountingDocument $invoice): AccountingDocument
    {
        if ($creditNote->company_id !== $invoice->company_id) {
            throw new \InvalidArgumentException('CREDIT_NOTE_COMPANY_MISMATCH');
        }

        if ($creditNote->type !== DocumentType::CreditNote->value || $invoice->type !== DocumentType::Invoice->value) {
            throw new \InvalidArgumentException('CREDIT_NOTE_LINK_TYPES_INVALID');
        }

        $creditNote->update(['source_document_id' => $invoice->id]);

        return $creditNote->refresh();
    }

    /**
     * Crée un document au statut draft avec ses lignes, totaux calculés et
     * numéro attribué (retry 23505 borné).
     *
     * @param  array{
     *     type?: string, contact_id?: int|null, project_ref?: string|null,
     *     issue_date?: string|null, due_date?: string|null, delivery_date?: string|null,
     *     currency?: string|null, tva_rate?: float|null, notes?: string|null,
     *     footer_mentions?: string|null, metadata?: array<string, mixed>,
     *     source_document_id?: int|null,
     *     lines?: list<array{description: string, quantity?: float, unit_price?: float, discount?: float, tax_id?: string|null, tax_rate?: float|null, tax_rate_code?: string|null}>
     * }  $payload
     */
    public function createDraft(array $payload, ?string $companyId = null): AccountingDocument
    {
        $type = DocumentType::tryFrom((string) ($payload['type'] ?? ''));
        if ($type === null) {
            throw new DocumentWorkflowException(__('accounting.errors.wf_invalid_type'));
        }

        $companyId ??= $this->currentCompanyId();

        // #9367 — cohérence du tiers : un document d'achat doit référencer un
        // fournisseur, un document de vente un client. Sans cette garde, une
        // facture fournisseur pouvait être rattachée à un client et le posting
        // créditait 401 pour une contrepartie qui n'existe pas au grand livre
        // auxiliaire.
        $this->assertContactMatchesType($type, $payload['contact_id'] ?? null, $companyId);

        $lines = $payload['lines'] ?? [];
        if ($lines === []) {
            throw new DocumentWorkflowException(__('accounting.errors.wf_requires_lines'));
        }

        // #9376 — TVA multi-taux. Chaque ligne peut porter son propre taux
        // (`tax_rate` explicite, ou `tax_rate_code` résolu dans le référentiel
        // du tenant). Le taux du document (`tva_rate`) reste le défaut des
        // lignes qui n'en précisent pas, ce qui préserve le comportement
        // mono-taux existant.
        $documentRate = isset($payload['tva_rate']) ? (float) $payload['tva_rate'] : null;
        $taxRates = $this->taxRates()->rateMapFor($companyId);
        $taxRateIds = $this->taxRates()->idMapFor($companyId);

        $subtotal = 0.0;
        $taxAmount = 0.0;
        $resolvedLines = [];

        foreach ($lines as $line) {
            $quantity = (float) ($line['quantity'] ?? 1);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $discount = (float) ($line['discount'] ?? 0);
            $lineSubtotal = round(max(0.0, $quantity * $unitPrice - $discount), 2);

            $rate = $this->resolveLineRate($line, $documentRate, $taxRates);
            $lineTax = round($lineSubtotal * $rate / 100, 2);

            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax;

            $resolvedLines[] = [
                'line' => $line,
                'subtotal' => $lineSubtotal,
                'rate' => $rate,
                'tax' => $lineTax,
                'tax_rate_id' => $this->resolveLineRateId($line, $taxRateIds),
            ];
        }

        $subtotal = round($subtotal, 2);
        $taxAmount = round($taxAmount, 2);
        $total = round($subtotal + $taxAmount, 2);

        // Taux « du document » : celui du payload s'il est fourni, sinon le taux
        // unique effectivement appliqué. Sur une facture multi-taux il n'y a pas
        // de taux unique : on laisse `tva_rate` à null plutôt que d'inventer une
        // moyenne, et les lignes portent la vérité.
        $distinctRates = array_values(array_unique(array_map(
            static fn (array $resolved): float => $resolved['rate'],
            $resolvedLines,
        )));
        $documentTvaRate = $documentRate ?? (count($distinctRates) === 1 ? $distinctRates[0] : null);

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        if (($payload['source_document_id'] ?? null) !== null) {
            $metadata['source_document_id'] = (int) $payload['source_document_id'];
        }

        $attempts = 0;
        do {
            try {
                return DB::transaction(function () use ($companyId, $type, $payload, $subtotal, $taxAmount, $total, $metadata, $resolvedLines, $documentTvaRate): AccountingDocument {
                    /** @var AccountingDocument $document */
                    $document = AccountingDocument::create([
                        'company_id' => $companyId,
                        'type' => $type->value,
                        'number' => $this->numbering->nextNumber($companyId, $type),
                        'status' => DocumentStatus::Draft->value,
                        'contact_id' => $payload['contact_id'] ?? null,
                        'project_ref' => $payload['project_ref'] ?? null,
                        'issue_date' => $payload['issue_date'] ?? now()->toDateString(),
                        'due_date' => $payload['due_date'] ?? null,
                        'delivery_date' => $payload['delivery_date'] ?? null,
                        'currency' => $payload['currency'] ?? null,
                        'tva_rate' => $documentTvaRate,
                        'subtotal_ht' => $subtotal,
                        'tax_amount' => $taxAmount,
                        'total_ttc' => $total,
                        'notes' => $payload['notes'] ?? null,
                        'footer_mentions' => $payload['footer_mentions'] ?? null,
                        'paid_amount' => 0,
                        'metadata' => $metadata,
                    ]);

                    $sortOrder = 0;
                    foreach ($resolvedLines as $resolved) {
                        $line = $resolved['line'];

                        AccountingDocumentLine::create([
                            'company_id' => $companyId,
                            'document_id' => $document->id,
                            'description' => $line['description'],
                            'quantity' => (float) ($line['quantity'] ?? 1),
                            'unit_price' => (float) ($line['unit_price'] ?? 0),
                            'discount' => (float) ($line['discount'] ?? 0),
                            'tax_rate_id' => $resolved['tax_rate_id'],
                            'tax_rate' => $resolved['rate'],
                            'tax_amount' => $resolved['tax'],
                            'sort_order' => $sortOrder++,
                        ]);
                    }

                    return $document->load('lines');
                });
            } catch (QueryException $exception) {
                // 23505 — numéro déjà pris par une création concurrente :
                // retry avec le candidat suivant (contrainte unique #5221).
                if ($exception->getCode() === '23505' && ++$attempts < self::MAX_NUMBERING_ATTEMPTS) {
                    continue;
                }

                throw $exception;
            }
        } while (true);
    }

    /**
     * draft → sent. Exige des lignes ; facture et avoir exigent un contact.
     */
    public function send(AccountingDocument $document, ?\Illuminate\Support\Carbon $sentAt = null): AccountingDocument
    {
        $this->assertStatus($document, [DocumentStatus::Draft], __('accounting.errors.wf_send_draft_only'));

        if ($document->lines()->count() === 0) {
            throw new DocumentWorkflowException(__('accounting.errors.wf_send_no_lines'));
        }

        if (in_array($document->type, [DocumentType::Invoice->value, DocumentType::CreditNote->value, DocumentType::PurchaseInvoice->value, DocumentType::PurchaseCreditNote->value], true)
            && $document->contact_id === null) {
            throw new DocumentWorkflowException(__('accounting.errors.wf_send_requires_contact'));
        }

        $document->forceFill([
            'status' => DocumentStatus::Sent->value,
            'sent_at' => $sentAt ?? now(),
        ])->save();

        return $document->refresh();
    }

    /**
     * Enregistre un paiement : crée AccountingPayment (statut recorded),
     * incrémente paid_amount et fait la transition partiel/payé. Un document
     * payé ou annulé ne peut pas recevoir de paiement ; le cumul ne peut pas
     * dépasser le total TTC.
     */
    public function recordPayment(
        AccountingDocument $document,
        float $amount,
        PaymentMethod $method,
        ?\Illuminate\Support\Carbon $receivedAt = null,
        ?string $reference = null,
    ): AccountingPayment {
        return DB::transaction(function () use ($document, $amount, $method, $receivedAt, $reference): AccountingPayment {
            // #6536 : verrou pessimiste + relecture dans la transaction —
            // sinon deux encaissements concurrents écrasent `paid_amount`
            // (lost update) et le document n'est jamais soldé.
            /** @var AccountingDocument $locked */
            $locked = AccountingDocument::query()
                ->lockForUpdate()
                ->findOrFail($document->id);

            $this->assertStatus($locked, [DocumentStatus::Draft, DocumentStatus::Sent, DocumentStatus::PartiallyPaid, DocumentStatus::Overdue], __('accounting.errors.wf_payment_receive_status'));

            if ($amount <= 0.0) {
                throw new DocumentWorkflowException(__('accounting.errors.payment_amount_positive'));
            }

            $newPaid = round((float) $locked->paid_amount + $amount, 2);
            if ($newPaid > round((float) $locked->total_ttc, 2) + 0.001) {
                throw new DocumentWorkflowException(__('accounting.errors.wf_payment_over_total'));
            }

            /** @var AccountingPayment $payment */
            $payment = AccountingPayment::create([
                'company_id' => $locked->company_id,
                'document_id' => $locked->id,
                'amount' => round($amount, 2),
                'method' => $method->value,
                'reference' => $reference,
                'received_at' => $receivedAt ?? now(),
                'status' => PaymentStatus::Recorded->value,
            ]);

            $locked->forceFill(['paid_amount' => $newPaid])->save();

            // Transition : payé uniquement quand le cumul couvre le total
            // (jamais de « paid » sans paiement — la garde statut ci-dessus).
            if ($newPaid >= round((float) $locked->total_ttc, 2) - 0.001) {
                $locked->forceFill(['status' => DocumentStatus::Paid->value])->save();
            } elseif ($locked->status === DocumentStatus::Draft->value || $locked->status === DocumentStatus::Sent->value) {
                $locked->forceFill(['status' => DocumentStatus::PartiallyPaid->value])->save();
            }

            return $payment;
        });
    }

    /**
     * Annule un document non payé (draft, sent, partially_paid, overdue).
     */
    public function cancel(AccountingDocument $document, ?string $reason = null): AccountingDocument
    {
        $this->assertStatus($document, [DocumentStatus::Draft, DocumentStatus::Sent, DocumentStatus::PartiallyPaid, DocumentStatus::Overdue], __('accounting.errors.wf_cancel_status'));

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        if ($reason !== null && $reason !== '') {
            $metadata['cancel_reason'] = $reason;
            $metadata['cancelled_at'] = now()->toIso8601String();
        }

        $document->forceFill([
            'status' => DocumentStatus::Cancelled->value,
            'metadata' => $metadata,
        ])->save();

        return $document->refresh();
    }

    /**
     * Crée un avoir lié à une facture source : montant borné au reste à
     * payer, lien via metadata.source_document_id.
     *
     * @param  array{lines: list<array{description: string, quantity?: float, unit_price?: float, discount?: float, tax_id?: string|null}>, issue_date?: string|null, notes?: string|null, metadata?: array<string, mixed>}  $payload
     */
    public function createCreditNote(AccountingDocument $source, array $payload): AccountingDocument
    {
        // #9367 — un avoir suit le sens de son document source : avoir de
        // vente sur une facture de vente, avoir d'achat sur une facture d'achat.
        $isPurchase = $source->type === DocumentType::PurchaseInvoice->value;

        if (! in_array($source->type, [DocumentType::Invoice->value, DocumentType::PurchaseInvoice->value], true)) {
            throw new DocumentWorkflowException(__('accounting.errors.wf_credit_note_requires_invoice'));
        }
        $this->assertStatus($source, [DocumentStatus::Sent, DocumentStatus::PartiallyPaid, DocumentStatus::Paid, DocumentStatus::Overdue], __('accounting.errors.wf_source_invoice_not_issuable'));

        $payload['type'] = $isPurchase
            ? DocumentType::PurchaseCreditNote->value
            : DocumentType::CreditNote->value;
        $payload['contact_id'] = $source->contact_id;
        $payload['metadata'] = array_merge(
            is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
            ['source_document_id' => $source->id],
        );

        $remaining = round($source->total_ttc - $source->paid_amount, 2);
        if ($remaining <= 0.001) {
            throw new DocumentWorkflowException(__('accounting.errors.wf_source_invoice_paid'));
        }

        $draft = $this->createDraft($payload, (string) $source->company_id);
        if ($draft->total_ttc > $remaining + 0.001) {
            // Rollback du brouillon : l'avoir dépasse le reste à payer.
            $draft->delete();

            throw new DocumentWorkflowException(__('accounting.errors.wf_credit_exceeds_remaining'));
        }

        return $draft;
    }

    /**
     * Rafraîchit les statuts overdue d'une entreprise : documents envoyés ou
     * partiellement payés dont l'échéance est dépassée. Retourne le nombre
     * de documents passés à overdue.
     */
    public function refreshOverdue(string $companyId): int
    {
        return AccountingDocument::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [DocumentStatus::Sent->value, DocumentStatus::PartiallyPaid->value])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => DocumentStatus::Overdue->value]);
    }

    /**
     * Un document est overdue si son échéance est dépassée et qu'il est
     * encore envoyé/partiellement payé.
     */
    public function isOverdue(AccountingDocument $document): bool
    {
        return $document->due_date !== null
            && $document->due_date->isBefore(now()->startOfDay())
            && in_array($document->status, [DocumentStatus::Sent->value, DocumentStatus::PartiallyPaid->value], true);
    }

    /**
     * Règles métier des transitions génériques (fusion #6572) : un statut
     * payé/partiellement payé exige le paiement correspondant, un avoir doit
     * être lié à sa facture source, un bordereau doit porter sa date de
     * livraison.
     */
    private function assertTransitionBusinessRules(AccountingDocument $document, DocumentStatus $current, DocumentStatus $to): void
    {
        $paidAmount = (float) $document->payments()->sum('amount');
        $totalTtc = (float) $document->total_ttc;

        if ($to === DocumentStatus::PartiallyPaid && $paidAmount <= 0) {
            throw new DocumentNotFullyPaidException($totalTtc, $paidAmount);
        }

        if ($to === DocumentStatus::PartiallyPaid && $paidAmount >= $totalTtc) {
            throw new DocumentNotFullyPaidException($totalTtc, $paidAmount);
        }

        if ($to === DocumentStatus::Paid && $paidAmount < $totalTtc) {
            throw new DocumentNotFullyPaidException($totalTtc, $paidAmount);
        }

        if (in_array($document->type, [DocumentType::CreditNote->value, DocumentType::PurchaseCreditNote->value], true)
            && $document->source_document_id === null) {
            throw new CreditNoteRequiresSourceInvoiceException;
        }

        if ($document->type === DocumentType::DeliveryNote->value && $document->delivery_date === null) {
            throw new DeliveryNoteRequiresDeliveryDateException;
        }
    }

    /** @param list<DocumentStatus> $allowed */
    private function assertStatus(AccountingDocument $document, array $allowed, string $message): void
    {
        if (! in_array(DocumentStatus::tryFrom($document->status), $allowed, true)) {
            throw new DocumentWorkflowException($message);
        }
    }

    /**
     * Le tiers référencé est-il compatible avec le sens du document ?
     *
     * #9367 — un document d'achat exige un `supplier`/`both`, un document de
     * vente exige un `customer`/`both`. Les types sans contrepartie exigée
     * (devis, proforma, BL) ne sont pas contrôlés : ils peuvent rester
     * orphelins tant qu'ils ne sont pas émis.
     */
    private function assertContactMatchesType(DocumentType $type, mixed $contactId, string $companyId): void
    {
        if ($contactId === null || $contactId === '') {
            return;
        }

        if (! $type->isPostable()) {
            return;
        }

        /** @var \App\Modules\Accounting\Domain\Models\AccountingContact|null $contact */
        $contact = \App\Modules\Accounting\Domain\Models\AccountingContact::query()
            ->where('company_id', $companyId)
            ->find((int) $contactId);

        if ($contact === null) {
            throw new DocumentWorkflowException(__('accounting.errors.wf_contact_not_found'));
        }

        $allowed = $type->isPurchase()
            ? [\App\Modules\Accounting\Domain\Enums\ContactType::Supplier->value, \App\Modules\Accounting\Domain\Enums\ContactType::Both->value]
            : [\App\Modules\Accounting\Domain\Enums\ContactType::Customer->value, \App\Modules\Accounting\Domain\Enums\ContactType::Both->value];

        if (! in_array((string) $contact->type, $allowed, true)) {
            throw new DocumentWorkflowException(__('accounting.errors.wf_contact_type_mismatch'));
        }
    }

    private function currentCompanyId(): string
    {
        if (! app()->bound('current_company')) {
            throw new DocumentWorkflowException(__('accounting.errors.wf_company_context'));
        }

        return (string) currentCompany()->id;
    }
}
