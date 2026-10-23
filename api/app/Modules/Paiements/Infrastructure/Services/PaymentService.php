<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services;

use App\Core\Tenant\Domain\Contracts\TenantScopedEvent;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Infrastructure\Services\TenantEventDispatcher;
use App\Events\PaymentCashOutCompleted;
use App\Events\PaymentCashOutRequested;
use App\Events\PaymentTransactionFailed;
use App\Events\PaymentTransactionSucceeded;
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
        private readonly TenantEventDispatcher $events,
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
            /** @var list<TenantScopedEvent> $pending */
            $pending = [];

            $transaction = DB::transaction(function () use (
                $companyId,
                $type,
                $amount,
                $currency,
                $idempotencyKey,
                $country,
                $metadata,
                $fromWalletId,
                $toWalletId,
                &$pending,
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

                // BC-38 (#8712) — un retrait est signale des sa CREATION, avant
                // toute ecriture comptable : le consommateur peut notifier
                // « retrait en cours » meme si le rail echoue ensuite (un
                // `payment.transaction.failed` suivra alors).
                if ($type === PaymentTransactionType::CashOut) {
                    $pending[] = new PaymentCashOutRequested(
                        $companyId,
                        (int) $transaction->id,
                        $amount,
                        $currency,
                    );
                }

                // Un echec de rail ne produit AUCUNE ecriture comptable :
                // l'argent n'a pas bouge, le ledger ne doit pas le dire.
                if ($railResult !== null && ! $railResult->isSucceeded()) {
                    $transaction->forceFill([
                        'status' => PaymentTransactionStatus::Failed->value,
                        'metadata' => array_merge($metadata, ['failure_reason' => $railResult->failureReason]),
                    ])->save();

                    $failed = $transaction->refresh();

                    $pending[] = new PaymentTransactionFailed(
                        $companyId,
                        (int) $failed->id,
                        $type->value,
                        (string) $railResult->failureReason,
                    );

                    return $failed;
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

                $succeeded = $transaction->refresh();

                $pending[] = new PaymentTransactionSucceeded(
                    $companyId,
                    (int) $succeeded->id,
                    $type->value,
                    $amount,
                    $currency,
                );

                // Le retrait abouti porte DEUX evenements : le generique
                // (transaction reussie) et l'intentionnel (sortie de fonds du
                // tenant), qui permet un rapprochement sans relire le type.
                if ($type === PaymentTransactionType::CashOut) {
                    $pending[] = new PaymentCashOutCompleted(
                        $companyId,
                        (int) $succeeded->id,
                        $amount,
                        $currency,
                    );
                }

                return $succeeded;
            });

            // BC-38 (#8712) — publication APRES le commit, jamais dedans :
            //  - un evenement ne doit pas partir pour un etat qui a ete rollback ;
            //  - un listener en echec ne doit pas annuler un paiement deja
            //    enregistre (les fonds ont bouge, l'evenement est secondaire) ;
            //  - `DB::afterCommit()` ne conviendrait pas : sous transaction
            //    englobante (tests, appelant transactionnel) il differerait le
            //    dispatch hors du cycle de vie observable.
            // Les evenements ont ete collectes dans `$pending` PENDANT la
            // transaction ; on les emet ici dans l'ordre metier.
            foreach ($pending as $event) {
                $this->publish($event, $companyId);
            }

            return $transaction;
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

    /**
     * BC-38 (#8712) — publication sur le bus tenant-scope.
     *
     * `TenantEventDispatcher` resout le tenant et echoue en fail-closed s'il
     * n'y en a pas : on lui passe la compagnie de la transaction, qui est
     * TOUJOURS la compagnie concernee (un job hors requete n'a pas de tenant
     * courant). Compagnie introuvable = pas d'evenement plutot qu'une
     * exception qui ferait echouer un paiement deja commite.
     */
    private function publish(TenantScopedEvent $event, string $companyId): void
    {
        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            return;
        }

        $this->events->dispatch($event, $company);
    }
}
