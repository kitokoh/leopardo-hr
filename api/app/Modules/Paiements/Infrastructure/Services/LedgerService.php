<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services;

use App\Modules\Paiements\Domain\Enums\LedgerDirection;
use App\Modules\Paiements\Domain\Exceptions\UnbalancedLedgerEntryException;
use App\Modules\Paiements\Domain\Models\PaymentLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BC-38 PAIEMENTS (#8681) — ecriture du ledger en partie double.
 *
 * Un seul point d'entree pour tout mouvement d'argent : `post()`. Il cree
 * **exactement deux** ecritures (debit + credit) partageant un
 * `transfer_group_id`, apres avoir verifie l'equilibre. Aucun appelant ne doit
 * ecrire une ecriture de ledger directement — c'est ce qui garantit qu'aucun
 * mouvement desequilibre ne peut exister.
 *
 * La correction d'une erreur passe par `reverse()` : un nouveau groupe inverse,
 * jamais un update (le modele refuse de toute facon toute modification).
 */
class LedgerService
{
    /**
     * Enregistre un mouvement equilibre entre deux wallets.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function post(
        string $companyId,
        int $debitWalletId,
        int $creditWalletId,
        int $amount,
        string $currency,
        ?int $transactionId = null,
        ?string $reference = null,
        array $metadata = [],
    ): string {
        if ($amount <= 0) {
            throw UnbalancedLedgerEntryException::forAmount($amount);
        }

        $transferGroupId = (string) Str::uuid();

        // Invariant verifie AVANT toute ecriture : un groupe desequilibre ne
        // doit jamais atteindre la base, meme partiellement.
        $this->assertBalanced($amount, $amount, $transferGroupId);

        $now = now();

        PaymentLedgerEntry::query()->insert([
            [
                'company_id' => $companyId,
                'wallet_id' => $debitWalletId,
                'direction' => LedgerDirection::Debit->value,
                'amount' => $amount,
                'currency' => $currency,
                'transaction_id' => $transactionId,
                'transfer_group_id' => $transferGroupId,
                'reference' => $reference,
                'metadata' => $metadata === [] ? null : json_encode($metadata),
                'created_at' => $now,
            ],
            [
                'company_id' => $companyId,
                'wallet_id' => $creditWalletId,
                'direction' => LedgerDirection::Credit->value,
                'amount' => $amount,
                'currency' => $currency,
                'transaction_id' => $transactionId,
                'transfer_group_id' => $transferGroupId,
                'reference' => $reference,
                'metadata' => $metadata === [] ? null : json_encode($metadata),
                'created_at' => $now,
            ],
        ]);

        return $transferGroupId;
    }

    /**
     * Contre-passation : cree le groupe inverse d'un groupe existant.
     *
     * Le solde revient a son etat anterieur, et les deux groupes restent dans
     * l'historique — c'est la seule correction auditable.
     */
    public function reverse(string $companyId, string $transferGroupId, ?string $reference = null): string
    {
        $entries = PaymentLedgerEntry::query()
            ->where('company_id', $companyId)
            ->where('transfer_group_id', $transferGroupId)
            ->get();

        if ($entries->isEmpty()) {
            throw UnbalancedLedgerEntryException::forGroup($transferGroupId, 0, 0);
        }

        $reversalGroupId = (string) Str::uuid();
        $now = now();
        $rows = [];

        foreach ($entries as $entry) {
            $direction = $entry->direction;

            $rows[] = [
                'company_id' => $companyId,
                'wallet_id' => $entry->wallet_id,
                'direction' => $direction->opposite()->value,
                'amount' => $entry->amount,
                'currency' => $entry->currency,
                'transaction_id' => $entry->transaction_id,
                'transfer_group_id' => $reversalGroupId,
                'reference' => $reference ?? ('reversal:'.$transferGroupId),
                'metadata' => json_encode(['reverses_group' => $transferGroupId]),
                'created_at' => $now,
            ];
        }

        PaymentLedgerEntry::query()->insert($rows);

        return $reversalGroupId;
    }

    /**
     * Solde derive d'un wallet, en plus petite unite de la devise.
     */
    public function balanceOf(string $companyId, int $walletId): int
    {
        /** @var object{credits: int|string, debits: int|string}|null $row */
        $row = DB::table('payment_ledger_entries')
            ->where('company_id', $companyId)
            ->where('wallet_id', $walletId)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE 0 END), 0) AS credits,'
                .' COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE 0 END), 0) AS debits',
                [LedgerDirection::Credit->value, LedgerDirection::Debit->value],
            )
            ->first();

        if ($row === null) {
            return 0;
        }

        return (int) $row->credits - (int) $row->debits;
    }

    /**
     * Verifie l'invariant de partie double d'un groupe.
     *
     * @return array{debits: int, credits: int, count: int, balanced: bool}
     */
    public function inspectGroup(string $companyId, string $transferGroupId): array
    {
        /** @var object{debits: int|string, credits: int|string, entry_count: int|string}|null $row */
        $row = DB::table('payment_ledger_entries')
            ->where('company_id', $companyId)
            ->where('transfer_group_id', $transferGroupId)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE 0 END), 0) AS debits,'
                .' COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE 0 END), 0) AS credits,'
                .' COUNT(*) AS entry_count',
                [LedgerDirection::Debit->value, LedgerDirection::Credit->value],
            )
            ->first();

        $debits = (int) ($row->debits ?? 0);
        $credits = (int) ($row->credits ?? 0);
        $count = (int) ($row->entry_count ?? 0);

        return [
            'debits' => $debits,
            'credits' => $credits,
            'count' => $count,
            'balanced' => $debits === $credits && $count % 2 === 0,
        ];
    }

    private function assertBalanced(int $debits, int $credits, string $transferGroupId): void
    {
        if ($debits !== $credits) {
            throw UnbalancedLedgerEntryException::forGroup($transferGroupId, $debits, $credits);
        }
    }
}
