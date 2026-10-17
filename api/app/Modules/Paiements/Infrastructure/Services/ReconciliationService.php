<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services;

use App\Modules\Paiements\Domain\Enums\ReconciliationStatus;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Paiements\Domain\Models\PaymentReconciliation;
use App\Modules\Paiements\Domain\Models\Wallet;

/**
 * BC-38 PAIEMENTS (#8692) — rapprochement solde externe / ledger.
 *
 * Le solde interne est recalcule depuis le ledger (seule source de verite) ; le
 * solde externe est celui que l'instance declare. L'ecart
 * `declared - computed` est conserve dans l'historique : un ecart corrige reste
 * une trace, c'est ce qui rend l'audit possible.
 */
class ReconciliationService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Solde interne d'une instance pour une devise : somme des mouvements des
     * wallets de la plateforme pour cette devise.
     */
    public function computedBalance(string $companyId, string $currency): int
    {
        $walletIds = Wallet::query()
            ->where('company_id', $companyId)
            ->where('currency', $currency)
            ->pluck('id')
            ->all();

        $total = 0;

        foreach ($walletIds as $walletId) {
            $total += $this->ledger->balanceOf($companyId, (int) $walletId);
        }

        return $total;
    }

    /**
     * Enregistre un rapprochement et retourne la ligne d'historique.
     */
    public function reconcile(
        string $companyId,
        PaymentProviderInstance $instance,
        string $currency,
        int $declaredBalance,
    ): PaymentReconciliation {
        $computed = $this->computedBalance($companyId, $currency);
        $difference = $declaredBalance - $computed;

        /** @var PaymentReconciliation $reconciliation */
        $reconciliation = PaymentReconciliation::query()->create([
            'company_id' => $companyId,
            'provider_instance_id' => $instance->id,
            'currency' => $currency,
            'declared_balance' => $declaredBalance,
            'computed_balance' => $computed,
            'difference' => $difference,
            'status' => ReconciliationStatus::fromDifference($difference)->value,
            'checked_at' => now(),
        ]);

        return $reconciliation;
    }
}
