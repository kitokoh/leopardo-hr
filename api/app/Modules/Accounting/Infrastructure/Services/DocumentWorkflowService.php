<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Exceptions\CreditNoteRequiresSourceInvoiceException;
use App\Modules\Accounting\Domain\Exceptions\DeliveryNoteRequiresDeliveryDateException;
use App\Modules\Accounting\Domain\Exceptions\DocumentNotFullyPaidException;
use App\Modules\Accounting\Domain\Exceptions\InvalidDocumentTransitionException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use Illuminate\Support\Carbon;

/**
 * Cycle de vie des documents comptables (issue #5223).
 *
 * Machine à états : draft → sent → partially_paid → paid | cancelled
 * (+ overdue calculé). Règles métier :
 *   - pas de `paid` sans paiement couvrant le total TTC ;
 *   - `partially_paid` exige un paiement partiel strict ;
 *   - un avoir (credit_note) doit être lié à sa facture source ;
 *   - un bordereau (delivery_note) doit porter sa date de livraison ;
 *   - `overdue` : sent/partially_paid dont l'échéance est dépassée.
 */
final class DocumentWorkflowService
{
    /**
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

    public function transition(AccountingDocument $document, DocumentStatus $to): AccountingDocument
    {
        $current = DocumentStatus::tryFrom($document->status) ?? DocumentStatus::Draft;

        $allowed = self::TRANSITIONS[$current->value];

        if (! in_array($to->value, $allowed, true)) {
            throw new InvalidDocumentTransitionException($current->value, $to->value, $allowed);
        }

        $this->assertBusinessRules($document, $current, $to);

        $document->update(['status' => $to->value]);

        return $document->refresh();
    }

    /**
     * Marque `overdue` les documents émis (sent/partially_paid) dont l'échéance
     * est dépassée. Retourne le nombre de documents mis à jour.
     */
    public function refreshOverdue(Company $company, ?Carbon $asOf = null): int
    {
        $asOf = $asOf ?? now($company->timezone);

        return AccountingDocument::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [DocumentStatus::Sent->value, DocumentStatus::PartiallyPaid->value])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $asOf->toDateString())
            ->update(['status' => DocumentStatus::Overdue->value]);
    }

    /**
     * Lie un avoir à sa facture source (même entreprise).
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

    private function assertBusinessRules(AccountingDocument $document, DocumentStatus $current, DocumentStatus $to): void
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

        if ($document->type === DocumentType::CreditNote->value && $document->source_document_id === null) {
            throw new CreditNoteRequiresSourceInvoiceException;
        }

        if ($document->type === DocumentType::DeliveryNote->value && $document->delivery_date === null) {
            throw new DeliveryNoteRequiresDeliveryDateException;
        }
    }
}
