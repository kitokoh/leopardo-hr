<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Services;

use App\Modules\Accounting\Domain\Contracts\DocumentNumberingInterface;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Enums\PaymentStatus;
use App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
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

    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
    ) {}

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
     *     lines?: list<array{description: string, quantity?: float, unit_price?: float, discount?: float, tax_id?: string|null}>
     * }  $payload
     */
    public function createDraft(array $payload, ?string $companyId = null): AccountingDocument
    {
        $type = DocumentType::tryFrom((string) ($payload['type'] ?? ''));
        if ($type === null) {
            throw new DocumentWorkflowException('Type de document invalide.');
        }

        $companyId ??= $this->currentCompanyId();

        $lines = $payload['lines'] ?? [];
        if ($lines === []) {
            throw new DocumentWorkflowException('Un document doit avoir au moins une ligne.');
        }

        $subtotal = 0.0;
        foreach ($lines as $line) {
            $quantity = (float) ($line['quantity'] ?? 1);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $discount = (float) ($line['discount'] ?? 0);
            $subtotal += max(0.0, $quantity * $unitPrice - $discount);
        }

        $tvaRate = isset($payload['tva_rate']) ? (float) $payload['tva_rate'] : 0.0;
        $taxAmount = round($subtotal * $tvaRate / 100, 2);
        $total = round($subtotal + $taxAmount, 2);

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        if (($payload['source_document_id'] ?? null) !== null) {
            $metadata['source_document_id'] = (int) $payload['source_document_id'];
        }

        $attempts = 0;
        do {
            try {
                return DB::transaction(function () use ($companyId, $type, $payload, $subtotal, $taxAmount, $total, $metadata, $lines): AccountingDocument {
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
                        'tva_rate' => isset($payload['tva_rate']) ? (float) $payload['tva_rate'] : null,
                        'subtotal_ht' => round($subtotal, 2),
                        'tax_amount' => $taxAmount,
                        'total_ttc' => $total,
                        'notes' => $payload['notes'] ?? null,
                        'footer_mentions' => $payload['footer_mentions'] ?? null,
                        'paid_amount' => 0,
                        'metadata' => $metadata,
                    ]);

                    $sortOrder = 0;
                    foreach ($lines as $line) {
                        AccountingDocumentLine::create([
                            'company_id' => $companyId,
                            'document_id' => $document->id,
                            'description' => $line['description'],
                            'quantity' => (float) ($line['quantity'] ?? 1),
                            'unit_price' => (float) ($line['unit_price'] ?? 0),
                            'discount' => (float) ($line['discount'] ?? 0),
                            'tax_id' => $line['tax_id'] ?? null,
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
    public function send(AccountingDocument $document, ?Carbon $sentAt = null): AccountingDocument
    {
        $this->assertStatus($document, [DocumentStatus::Draft], 'Seul un brouillon peut être envoyé.');

        if ($document->lines()->count() === 0) {
            throw new DocumentWorkflowException('Impossible d\'envoyer un document sans ligne.');
        }

        if (in_array($document->type, [DocumentType::Invoice->value, DocumentType::CreditNote->value], true)
            && $document->contact_id === null) {
            throw new DocumentWorkflowException('Un contact client est requis pour envoyer une facture ou un avoir.');
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
        ?Carbon $receivedAt = null,
        ?string $reference = null,
    ): AccountingPayment {
        $this->assertStatus($document, [DocumentStatus::Draft, DocumentStatus::Sent, DocumentStatus::PartiallyPaid, DocumentStatus::Overdue], 'Un document payé ou annulé ne peut pas recevoir de paiement.');

        if ($amount <= 0.0) {
            throw new DocumentWorkflowException('Le montant du paiement doit être strictement positif.');
        }

        $newPaid = round($document->paid_amount + $amount, 2);
        if ($newPaid > round($document->total_ttc, 2) + 0.001) {
            throw new DocumentWorkflowException('Le cumul des paiements dépasse le total TTC du document.');
        }

        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::create([
            'company_id' => $document->company_id,
            'document_id' => $document->id,
            'amount' => round($amount, 2),
            'method' => $method->value,
            'reference' => $reference,
            'received_at' => $receivedAt ?? now(),
            'status' => PaymentStatus::Recorded->value,
        ]);

        $document->forceFill(['paid_amount' => $newPaid])->save();

        // Transition : payé uniquement quand le cumul couvre le total
        // (jamais de « paid » sans paiement — la garde statut ci-dessus).
        if ($newPaid >= round($document->total_ttc, 2) - 0.001) {
            $document->forceFill(['status' => DocumentStatus::Paid->value])->save();
        } elseif ($document->status === DocumentStatus::Draft->value || $document->status === DocumentStatus::Sent->value) {
            $document->forceFill(['status' => DocumentStatus::PartiallyPaid->value])->save();
        }

        return $payment;
    }

    /**
     * Annule un document non payé (draft, sent, partially_paid, overdue).
     */
    public function cancel(AccountingDocument $document, ?string $reason = null): AccountingDocument
    {
        $this->assertStatus($document, [DocumentStatus::Draft, DocumentStatus::Sent, DocumentStatus::PartiallyPaid, DocumentStatus::Overdue], 'Un document payé ne peut pas être annulé.');

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
        if ($source->type !== DocumentType::Invoice->value) {
            throw new DocumentWorkflowException('Un avoir doit être lié à une facture.');
        }
        $this->assertStatus($source, [DocumentStatus::Sent, DocumentStatus::PartiallyPaid, DocumentStatus::Paid, DocumentStatus::Overdue], 'Une facture annulée ou brouillon ne peut pas générer d\'avoir.');

        $payload['type'] = DocumentType::CreditNote->value;
        $payload['contact_id'] = $source->contact_id;
        $payload['metadata'] = array_merge(
            is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
            ['source_document_id' => $source->id],
        );

        $remaining = round($source->total_ttc - $source->paid_amount, 2);
        if ($remaining <= 0.001) {
            throw new DocumentWorkflowException('La facture source est déjà entièrement payée : aucun avoir possible.');
        }

        $draft = $this->createDraft($payload, (string) $source->company_id);
        if ($draft->total_ttc > $remaining + 0.001) {
            // Rollback du brouillon : l'avoir dépasse le reste à payer.
            $draft->delete();

            throw new DocumentWorkflowException('Le montant de l\'avoir dépasse le reste à payer de la facture source.');
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
     * @param  list<DocumentStatus>  $allowed
     */
    private function assertStatus(AccountingDocument $document, array $allowed, string $message): void
    {
        if (! in_array(DocumentStatus::tryFrom($document->status), $allowed, true)) {
            throw new DocumentWorkflowException($message);
        }
    }

    private function currentCompanyId(): string
    {
        if (! app()->bound('current_company')) {
            throw new DocumentWorkflowException('Contexte entreprise requis.');
        }

        return (string) currentCompany()->id;
    }
}
