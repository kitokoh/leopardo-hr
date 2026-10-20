<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Contracts;

use App\Modules\Paiements\Domain\Enums\WalletOwnerType;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Modules\Paiements\Domain\Models\Wallet;

/**
 * BC-38 PAIEMENTS (#8680) — contrat des operations financieres.
 *
 * Cette interface vit dans le **Domain** pour que la couche Application puisse
 * orchestrer des paiements sans dependre de l'Infrastructure (regle de purete
 * des couches : Domain/Application ne connaissent jamais Infrastructure).
 *
 * L'implementation concrete (`PaymentService`) est liee dans le provider du
 * module. C'est l'inversion de dependance classique : la politique metier
 * depend d'une abstraction, le mecanisme depend de la politique.
 */
interface PaymentOperationsInterface
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function cashIn(
        string $companyId,
        int $amount,
        string $currency,
        string $idempotencyKey,
        ?string $country = null,
        array $metadata = [],
    ): PaymentTransaction;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function cashOut(
        string $companyId,
        int $amount,
        string $currency,
        string $idempotencyKey,
        ?string $country = null,
        array $metadata = [],
    ): PaymentTransaction;

    /**
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
    ): PaymentTransaction;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function refund(
        string $companyId,
        int $amount,
        string $currency,
        string $idempotencyKey,
        string $originalTransferGroupId,
        array $metadata = [],
    ): PaymentTransaction;

    public function walletFor(
        string $companyId,
        WalletOwnerType $ownerType,
        string $ownerId,
        string $currency,
    ): Wallet;
}
