<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Exceptions\PaymentExceedsTotalException;
use App\Modules\Accounting\Domain\Exceptions\PaymentOnUnsentDocumentException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Trésorerie Phase B (issue #5229) — enregistrement, rapprochement et liste
 * des paiements.
 *
 * Règles :
 *   - « jamais payé > total » : `paid_amount + montant ≤ total_ttc`, sinon
 *     `PaymentExceedsTotalException` (422) — rien n'est écrit ;
 *   - seuls les documents émis (≠ draft/cancelled) acceptent des paiements ;
 *   - l'enregistrement crée le paiement en `recorded` et met à jour
 *     `paid_amount` + statut du document (partially_paid/paid) — transition
 *     minimale locale ; le workflow complet de documents est porté par #5223
 *     (PR #5346) ;
 *   - le rapprochement (`reconcile`) marque `matched` + `reconciled_at`,
 *     idempotent (re-rapprocher un paiement déjà matched est sans effet).
 */
final class PaymentRegistrationService
{
    private const TOLERANCE = 0.005;

    public function register(
        AccountingDocument $document,
        float $amount,
        string $method,
        ?string $reference = null,
        ?Carbon $receivedAt = null,
    ): AccountingPayment {
        if (in_array($document->status, [DocumentStatus::Draft->value, DocumentStatus::Cancelled->value], true)) {
            throw new PaymentOnUnsentDocumentException((string) $document->status);
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant du paiement doit être strictement positif.');
        }

        $alreadyPaid = (float) $document->paid_amount;
        $total = (float) $document->total_ttc;

        if ($alreadyPaid + $amount > $total + self::TOLERANCE) {
            throw new PaymentExceedsTotalException($total, $alreadyPaid, $amount);
        }

        return DB::transaction(function () use ($document, $amount, $method, $reference, $receivedAt): AccountingPayment {
            /** @var AccountingPayment $payment */
            $payment = AccountingPayment::create([
                'document_id' => $document->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'received_at' => $receivedAt ?? Carbon::today(),
                'status' => 'recorded',
            ]);

            $newPaidAmount = round((float) $document->paid_amount + $amount, 2);
            $document->update(['paid_amount' => $newPaidAmount]);

            // Statut de document minimal : paid dès que soldé, sinon partially_paid.
            if (in_array($document->status, [DocumentStatus::Sent->value, DocumentStatus::PartiallyPaid->value, DocumentStatus::Overdue->value], true)) {
                $newStatus = $newPaidAmount >= (float) $document->total_ttc - self::TOLERANCE
                    ? DocumentStatus::Paid->value
                    : DocumentStatus::PartiallyPaid->value;
                $document->update(['status' => $newStatus]);
            }

            return $payment;
        });
    }

    /**
     * Rapprochement manuel : pending/recorded → matched + reconciled_at.
     * Idempotent : un paiement déjà rapproché n'est pas modifié.
     */
    public function reconcile(AccountingPayment $payment): AccountingPayment
    {
        if ($payment->status === 'matched') {
            return $payment;
        }

        $payment->update(['status' => 'matched', 'reconciled_at' => Carbon::now()]);

        return $payment->refresh();
    }

    /**
     * Liste des paiements (filtres document et/ou statut), du plus récent au
     * plus ancien. Scopée tenant (BelongsToCompany).
     *
     * @return Collection<int, AccountingPayment>
     */
    public function list(?int $documentId = null, ?string $status = null): Collection
    {
        $query = AccountingPayment::query()->orderByDesc('received_at')->orderByDesc('id');

        if ($documentId !== null) {
            $query->where('document_id', $documentId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }
}
