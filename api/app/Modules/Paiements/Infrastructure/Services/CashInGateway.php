<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services;

use App\Modules\Paiements\Domain\Contracts\PaymentOperationsInterface;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Shared\Contracts\Payments\CashInGatewayInterface;

/**
 * BC-38 PAIEMENTS (#9238) — implémentation du contrat partagé d'encaissement.
 *
 * Rôle volontairement MINCE : traduire un appel de consommateur (Library,
 * demain une autre verticale) en `PaymentService::cashIn()`, puis exposer le
 * résultat sous forme de tableau normalisé. Aucune logique métier ici — le
 * ledger, l'idempotence en base et la résolution du rail restent dans
 * `PaymentService`, leur propriétaire unique.
 *
 * Conséquence importante pour les appelants : le statut retourné est celui de
 * la TRANSACTION au moment de l'initiation. Un rail asynchrone répond
 * typiquement `processing` — ce n'est pas une autorisation d'accès.
 */
class CashInGateway implements CashInGatewayInterface
{
    public function __construct(
        private readonly PaymentOperationsInterface $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{transaction_id: int, status: string, external_reference: string|null}
     */
    public function cashIn(
        string $companyId,
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
        ?string $country = null,
        array $metadata = [],
    ): array {
        $transaction = $this->payments->cashIn(
            $companyId,
            $amountMinor,
            $currency,
            $idempotencyKey,
            $country,
            $metadata,
        );

        return [
            'transaction_id' => (int) $transaction->id,
            'status' => $transaction->status->value,
            'external_reference' => $transaction->external_reference,
        ];
    }

    /**
     * @return array{status: string, amount_minor: int, currency: string}|null
     */
    public function transactionStatus(int $transactionId, ?string $companyId = null): ?array
    {
        // Le scope tenant du modèle s'applique de lui-même quand un contexte
        // courant existe (règle #7960 : pas de `withoutGlobalScope` brut).
        $query = PaymentTransaction::query()->whereKey($transactionId);

        // Le tenant est TOUJOURS appliqué ici : une transaction d'un autre
        // tenant est indiscernable d'une transaction inexistante pour
        // l'appelant (aucune fuite d'existence).
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        /** @var PaymentTransaction|null $transaction */
        $transaction = $query->first();

        if (! $transaction instanceof PaymentTransaction) {
            return null;
        }

        return [
            'status' => $transaction->status->value,
            'amount_minor' => (int) $transaction->amount,
            'currency' => (string) $transaction->currency,
        ];
    }
}
