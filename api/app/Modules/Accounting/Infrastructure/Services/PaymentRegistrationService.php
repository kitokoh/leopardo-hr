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
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('accounting.errors.payment_amount_positive'));
        }

        // #6536 (audit fiabilité 2026-08-31) — toute la séquence
        // garde→création→update se fait SOUS verrou de ligne : deux
        // encaissements concurrents (double-clic, webhook Stripe + saisie
        // manuelle) sérialisent ici. Avant : le garde lisait `paid_amount`
        // hors transaction et l'update réécrivait la valeur stale du modèle
        // → cumul réel 2× mais `paid_amount` sous-évalué, document jamais
        // `paid`, journal en déséquilibre.
        return DB::transaction(function () use ($document, $amount, $method, $reference, $receivedAt, $gatewayPaymentId): AccountingPayment {
            /** @var AccountingDocument|null $locked */
            $locked = AccountingDocument::query()
                // Le scope tenant est contourné explicitement : l'isolation est
                // portée par le WHERE company_id ci-dessous (déterministe aussi
                // en console/jobs/tests, cf. BelongsToCompany docblock).
                ->withoutGlobalScope('company')
                ->whereKey($document->getKey())
                ->where('company_id', $document->company_id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new \RuntimeException(__('accounting.errors.wf_document_missing_for_payment'));
            }

            if (in_array($locked->status, [DocumentStatus::Draft->value, DocumentStatus::Cancelled->value], true)) {
                throw new PaymentOnUnsentDocumentException((string) $locked->status);
            }

            // Relecture APRÈS lockForUpdate : paid_amount est la valeur
            // courante en base, plus jamais la copie stale du modèle passé.
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
                $newStatus = $newPaidAmount >= $total - self::TOLERANCE
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
