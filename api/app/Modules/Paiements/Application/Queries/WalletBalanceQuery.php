<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Application\Queries;

use App\Modules\Paiements\Domain\Models\PaymentLedgerEntry;
use App\Modules\Paiements\Domain\Models\Wallet;

/**
 * BC-38 PAIEMENTS (#8691) — lecture du wallet d'un tenant.
 *
 * Le solde est toujours recalcule depuis le ledger : cette classe ne fait que
 * presenter, elle ne met rien en cache et ne stocke rien.
 */
final class WalletBalanceQuery
{
    /**
     * @return array{wallet_id: int, currency: string, balance: int}
     */
    public function forWallet(Wallet $wallet): array
    {
        return [
            'wallet_id' => (int) $wallet->id,
            'currency' => (string) $wallet->currency,
            'balance' => $wallet->balance(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(string $companyId, int $limit = 100): array
    {
        $entries = PaymentLedgerEntry::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = [
                'id' => $entry->id,
                'wallet_id' => $entry->wallet_id,
                'direction' => $entry->direction->value,
                'amount' => $entry->amount,
                'currency' => $entry->currency,
                'transfer_group_id' => $entry->transfer_group_id,
                'reference' => $entry->reference,
                'created_at' => $entry->created_at?->toIso8601String(),
            ];
        }

        return $rows;
    }
}
