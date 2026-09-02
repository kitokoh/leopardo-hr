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
        ?string $gatewayPaymentId = null,
    ): AccountingPayment {
        if (in_array($document->status, [DocumentStatus::Draft->value, DocumentStatus::Cancelled->value], true)) {
            throw new PaymentOnUnsentDocumentException((string) $document->status);
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('accounting.errors.payment_amount_positive'));
        }

        return DB::transaction(function () use ($document, $amount, $method, $reference, $receivedAt, $gatewayPaymentId): AccountingPayment {
            // #6536 : verrou pessimiste + relecture DANS la transaction. Le
            // garde « jamais payé > total » et l'update de `paid_amount`
            // doivent lire la MÊME valeur fraîche : sans `lockForUpdate`,
            // deux encaissements concurrents (double-clic, webhook Stripe +
            // saisie manuelle) écrasent `paid_amount` → cumul réel 2× mais
            // document jamais `paid` et journal en déséquilibre.
            /** @var AccountingDocument $locked */
            $locked = AccountingDocument::query()
                ->lockForUpdate()
                ->findOrFail($document->id);

            $alreadyPaid = (float) $locked->paid_amount;
            $total = (float) $locked->total_ttc;

            if ($alreadyPaid + $amount > $total + self::TOLERANCE) {
                throw new PaymentExceedsTotalException($total, $alreadyPaid, $amount);
            }

            /** @var AccountingPayment $payment */
            $payment = AccountingPayment::create([
                // company_id dérivé du document — indépendant du contexte
                // tenant (API, console, tests) : jamais null (NOT NULL).
                'company_id' => $locked->company_id,
                'document_id' => $locked->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'gateway_payment_id' => $gatewayPaymentId,
                'received_at' => $receivedAt ?? Carbon::today(),
                'status' => 'recorded',
            ]);

            $newPaidAmount = round($alreadyPaid + $amount, 2);
            $locked->update(['paid_amount' => $newPaidAmount]);

            // Statut de document minimal : paid dès que soldé, sinon partially_paid.
            if (in_array($locked->status, [DocumentStatus::Sent->value, DocumentStatus::PartiallyPaid->value, DocumentStatus::Overdue->value], true)) {
                $newStatus = $newPaidAmount >= (float) $locked->total_ttc - self::TOLERANCE
                    ? DocumentStatus::Paid->value
                    : DocumentStatus::PartiallyPaid->value;
                $locked->update(['status' => $newStatus]);
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
    /**
     * Issue #6562 — limit optionnel pour borner les listes non paginees.
     */
    /**
     * @return Collection<int, AccountingPayment>
     */
    public function list(?int $documentId = null, ?string $status = null, ?int $limit = null): Collection
    {
        $query = AccountingPayment::query()->orderByDesc('received_at')->orderByDesc('id');

        if ($documentId !== null) {
            $query->where('document_id', $documentId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
