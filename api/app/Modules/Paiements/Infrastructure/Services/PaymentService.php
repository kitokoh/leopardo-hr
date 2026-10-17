<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services;

use App\Modules\Paiements\Domain\Contracts\PaymentOperationsInterface;
use App\Modules\Paiements\Domain\DTOs\CashInRequest;
use App\Modules\Paiements\Domain\DTOs\CashOutRequest;
use App\Modules\Paiements\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Paiements\Domain\Enums\PaymentTransactionType;
use App\Modules\Paiements\Domain\Enums\WalletOwnerType;
use App\Modules\Paiements\Domain\Exceptions\IdempotencyKeyRequiredException;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Modules\Paiements\Domain\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * BC-38 PAIEMENTS (#8680/#8682/#8689) — orchestration des operations financieres.
 *
 * C'est l'unique porte d'entree pour bouger de l'argent. Trois garanties y sont
 * concentrees, parce qu'elles ne peuvent pas etre deleguees :
 *
 * 1. **Idempotence** — la cle est unique en base `(company_id, idempotency_key)`.
 *    Un rejeu retourne la transaction existante sans rien rejouer. Le verrou
 *    `lockForUpdate()` serialise les appels concurrents, et la violation de
 *    contrainte unique (23505) est rattrapee : c'est la base, pas un `if`, qui
 *    garantit l'unicite. Un rejeu concurrent produit donc exactement une paire
 *    d'ecritures.
 *
 * 2. **Partie double** — toute ecriture passe par `LedgerService::post()`, qui
 *    cree les deux ecritures liees. Aucun chemin ne contourne le ledger.
 *
 * 3. **Aucun `if simulateur`** — le rail est choisi par la configuration via
 *    `ProviderResolver` + `ProviderTypeRegistry`. Le simulateur et un futur
 *    fournisseur reel empruntent le meme code.
 */
class PaymentService implements PaymentOperationsInterface
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ProviderResolver $resolver,
        private readonly ProviderTypeRegistry $registry,
        private readonly FeeRuleResolver $fees,
    ) {}

    /**
     * Encaissement : l'argent entre sur le wallet du tenant.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function cashIn(
        string $companyId,
        int $amount,
        string $currency,
        string $idempotencyKey,
        ?string $country = null,
        array $metadata = [],
    ): PaymentTransaction {
        return $this->execute(
            companyId: $companyId,
            type: PaymentTransactionType::CashIn,
            amount: $amount,
            currency: $currency,
            idempotencyKey: $idempotencyKey,
            country: $country,
            metadata: $metadata,
        );
    }

    /**
     * Decaissement : l'argent sort du wallet du tenant.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function cashOut(
        string $companyId,
        int $amount,
        string $currency,
        string $idempotencyKey,
        ?string $country = null,
        array $metadata = [],
    ): PaymentTransaction {
        return $this->execute(
            companyId: $companyId,
            type: PaymentTransactionType::CashOut,
            amount: $amount,
            currency: $currency,
            idempotencyKey: $idempotencyKey,
            country: $country,
            metadata: $metadata,
        );
    }

    /**
     * Transfert strictement interne : aucun rail externe, donc
     * `provider_instance_id` reste NULL.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function internalTransfer(
        string $companyId,
        int $amount,
        string $currency,
        string $idempotencyKey,
        int $fromWalletId,
        int $toWalletId,
        array $metadata = [],
    ): PaymentTransaction {
        return $this->execute(
            companyId: $companyId,
            type: PaymentTransactionType::InternalTransfer,
            amount: $amount,
            currency: $currency,
            idempotencyKey: $idempotencyKey,
            country: null,
            metadata: $metadata,
            fromWalletId: $fromWalletId,
            toWalletId: $toWalletId,
        );
    }

    /**
     * Remboursement : contre-passation du mouvement d'origine.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function refund(
        string $companyId,
        int $amount,
        string $currency,
        string $idempotencyKey,
        string $originalTransferGroupId,
        array $metadata = [],
    ): PaymentTransaction {
        return DB::transaction(function () use (
            $companyId,
            $amount,
            $currency,
            $idempotencyKey,
            $originalTransferGroupId,
            $metadata,
        ): PaymentTransaction {
            $existing = $this->findByIdempotencyKey($companyId, $idempotencyKey);

            if ($existing !== null) {
                return $existing;
            }

            $transaction = $this->createTransaction(
                companyId: $companyId,
                type: PaymentTransactionType::Refund,
                amount: $amount,
                currency: $currency,
                idempotencyKey: $idempotencyKey,
                providerInstanceId: null,
                walletId: null,
                counterpartyWalletId: null,
                metadata: $metadata,
            );

            $reversalGroupId = $this->ledger->reverse(
                $companyId,
                $originalTransferGroupId,
                'refund:'.$idempotencyKey,
            );

            $transaction->forceFill([
                'status' => PaymentTransactionStatus::Succeeded->value,
                'external_reference' => $reversalGroupId,
            ])->save();

            return $transaction->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function execute(
        string $companyId,
        PaymentTransactionType $type,
        int $amount,
        string $currency,
        string $idempotencyKey,
        ?string $country,
        array $metadata,
        ?int $fromWalletId = null,
        ?int $toWalletId = null,
    ): PaymentTransaction {
        if (trim($idempotencyKey) === '') {
            throw new IdempotencyKeyRequiredException;
        }

        try {
            return DB::transaction(function () use (
                $companyId,
                $type,
                $amount,
                $currency,
                $idempotencyKey,
                $country,
                $metadata,
                $fromWalletId,
                $toWalletId,
            ): PaymentTransaction {
                // Rejeu : on retourne le resultat existant, sans effet de bord.
                $existing = $this->findByIdempotencyKey($companyId, $idempotencyKey, lock: true);

                if ($existing !== null) {
                    return $existing;
                }

                $providerInstance = null;
                $railResult = null;

                if ($type->requiresProvider()) {
                    $providerInstance = $this->resolver->resolve(
                        $country ?? 'DZ',
                        $currency,
                        $companyId,
                    );

                    $rail = $this->registry->make($providerInstance->type_code);
                    $config = $providerInstance->config ?? [];

                    $railResult = $type === PaymentTransactionType::CashIn
                        ? $rail->initierCashIn(new CashInRequest($companyId, $amount, $currency, $idempotencyKey, $config, $metadata))
                        : $rail->initierCashOut(new CashOutRequest($companyId, $amount, $currency, $idempotencyKey, $config, $metadata));
                }

                $fee = $this->fees->feeFor(
                    $amount,
                    $type->value,
                    $companyId,
                    $providerInstance?->id,
                );

                $transaction = $this->createTransaction(
                    companyId: $companyId,
                    type: $type,
                    amount: $amount,
                    currency: $currency,
                    idempotencyKey: $idempotencyKey,
                    providerInstanceId: $providerInstance?->id,
                    walletId: $fromWalletId,
                    counterpartyWalletId: $toWalletId,
                    metadata: $metadata,
                    feeAmount: $fee,
                );

                // Un echec de rail ne produit AUCUNE ecriture comptable :
                // l'argent n'a pas bouge, le ledger ne doit pas le dire.
                if ($railResult !== null && ! $railResult->isSucceeded()) {
                    $transaction->forceFill([
                        'status' => PaymentTransactionStatus::Failed->value,
                        'metadata' => array_merge($metadata, ['failure_reason' => $railResult->failureReason]),
                    ])->save();

                    return $transaction->refresh();
                }

                $this->postLedger(
                    companyId: $companyId,
                    type: $type,
                    amount: $amount,
                    currency: $currency,
                    transactionId: (int) $transaction->id,
                    idempotencyKey: $idempotencyKey,
                    fromWalletId: $fromWalletId,
                    toWalletId: $toWalletId,
                );

                $transaction->forceFill([
                    'status' => PaymentTransactionStatus::Succeeded->value,
                    'external_reference' => $railResult?->externalReference,
                ])->save();

                return $transaction->refresh();
            });
        } catch (QueryException $exception) {
            // Course concurrente : la contrainte unique a tranche. Le perdant
            // retourne la transaction du gagnant — une seule paire d'ecritures.
            if ($this->isUniqueViolation($exception)) {
                $existing = $this->findByIdempotencyKey($companyId, $idempotencyKey);

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    private function postLedger(
        string $companyId,
        PaymentTransactionType $type,
        int $amount,
        string $currency,
        int $transactionId,
        string $idempotencyKey,
        ?int $fromWalletId,
        ?int $toWalletId,
    ): void {
        $tenantWallet = $this->walletFor($companyId, WalletOwnerType::Tenant, $companyId, $currency);
        $platformWallet = $this->walletFor($companyId, WalletOwnerType::Platform, 'platform', $currency);

        match ($type) {
            // Encaissement : le tenant recoit, la plateforme avance.
            PaymentTransactionType::CashIn => $this->ledger->post(
                $companyId,
                (int) $platformWallet->id,
                (int) $tenantWallet->id,
                $amount,
                $currency,
                $transactionId,
                $idempotencyKey,
            ),
            // Decaissement : le tenant sort, la plateforme encaisse.
            PaymentTransactionType::CashOut => $this->ledger->post(
                $companyId,
                (int) $tenantWallet->id,
                (int) $platformWallet->id,
                $amount,
                $currency,
                $transactionId,
                $idempotencyKey,
            ),
            // Transfert interne : entre deux wallets explicites.
            PaymentTransactionType::InternalTransfer => $this->ledger->post(
                $companyId,
                $fromWalletId ?? (int) $tenantWallet->id,
                $toWalletId ?? (int) $platformWallet->id,
                $amount,
                $currency,
                $transactionId,
                $idempotencyKey,
            ),
            // Le remboursement passe par `reverse()`, pas par ici.
            PaymentTransactionType::Refund => null,
        };
    }

    public function walletFor(
        string $companyId,
        WalletOwnerType $ownerType,
        string $ownerId,
        string $currency,
    ): Wallet {
        /** @var Wallet $wallet */
        $wallet = Wallet::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'owner_type' => $ownerType->value,
                'owner_id' => $ownerId,
                'currency' => $currency,
            ],
        );

        return $wallet;
    }

    private function findByIdempotencyKey(string $companyId, string $idempotencyKey, bool $lock = false): ?PaymentTransaction
    {
        $query = PaymentTransaction::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $idempotencyKey);

        if ($lock) {
            $query->lockForUpdate();
        }

        /** @var PaymentTransaction|null $transaction */
        $transaction = $query->first();

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createTransaction(
        string $companyId,
        PaymentTransactionType $type,
        int $amount,
        string $currency,
        string $idempotencyKey,
        ?int $providerInstanceId,
        ?int $walletId,
        ?int $counterpartyWalletId,
        array $metadata,
        int $feeAmount = 0,
    ): PaymentTransaction {
        /** @var PaymentTransaction $transaction */
        $transaction = PaymentTransaction::query()->create([
            'company_id' => $companyId,
            'type' => $type->value,
            'status' => PaymentTransactionStatus::Pending->value,
            'provider_instance_id' => $providerInstanceId,
            'wallet_id' => $walletId,
            'counterparty_wallet_id' => $counterpartyWalletId,
            'amount' => $amount,
            'fee_amount' => $feeAmount,
            'currency' => $currency,
            'idempotency_key' => $idempotencyKey,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);

        return $transaction;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23505'
            || str_contains($exception->getMessage(), 'payment_transactions_idempotency_unique');
    }
}
