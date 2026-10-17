<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Application\Actions;

use App\Modules\Paiements\Domain\Enums\WalletOwnerType;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Modules\Paiements\Infrastructure\Services\PaymentService;

/**
 * BC-38 PAIEMENTS (#8691) — cas d'usage : demande de retrait d'un tenant.
 *
 * Couche Application : elle orchestre, elle ne decide pas. Le marche est
 * resolu par le `ProviderResolver` (via `PaymentService`), jamais par
 * l'appelant — c'est la regle structurante du BC : aucun consommateur ne
 * choisit son fournisseur.
 */
final class RequestCashOutAction
{
    public function __construct(private readonly PaymentService $payments) {}

    public function execute(
        string $companyId,
        int $amount,
        string $currency,
        string $idempotencyKey,
        ?string $country = null,
    ): PaymentTransaction {
        return $this->payments->cashOut(
            companyId: $companyId,
            amount: $amount,
            currency: $currency,
            idempotencyKey: $idempotencyKey,
            country: $country,
        );
    }

    /**
     * Wallet du tenant pour une devise — cree a la demande, jamais de solde
     * stocke.
     */
    public function tenantWallet(string $companyId, string $currency): \App\Modules\Paiements\Domain\Models\Wallet
    {
        return $this->payments->walletFor(
            $companyId,
            WalletOwnerType::Tenant,
            $companyId,
            $currency,
        );
    }
}
