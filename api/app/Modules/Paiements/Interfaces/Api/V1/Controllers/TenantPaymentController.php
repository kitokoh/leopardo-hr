<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Interfaces\Api\V1\Controllers;

use App\Modules\Paiements\Domain\Enums\WalletOwnerType;
use App\Modules\Paiements\Domain\Exceptions\ProviderNotResolvableException;
use App\Modules\Paiements\Domain\Models\PaymentLedgerEntry;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Modules\Paiements\Infrastructure\Services\PaymentService;
use App\Modules\Paiements\Infrastructure\Services\ProviderResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BC-38 PAIEMENTS (#8691) — surface Tenant.
 *
 * Le tenant voit son solde, son historique et ses commissions, et demande un
 * retrait. Il ne choisit **jamais** l'instance : le marche (pays + devise) est
 * resolu cote serveur par `ProviderResolver`. Laisser le client choisir son
 * fournisseur serait a la fois une fuite d'architecture et un risque de
 * contournement de la configuration plateforme.
 *
 * Le scoping tenant est assure par le scope global `company` du trait
 * `BelongsToCompany` : un tenant ne peut pas lire les donnees d'un autre.
 */
class TenantPaymentController
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly ProviderResolver $resolver,
    ) {}

    public function balance(Request $request): JsonResponse
    {
        $company = currentCompany();
        $currency = (string) $request->query('currency', $company->currency ?? 'DZD');

        $wallet = $this->payments->walletFor(
            (string) $company->id,
            WalletOwnerType::Tenant,
            (string) $company->id,
            $currency,
        );

        return response()->json([
            'code' => 'ok',
            'message' => 'Solde du wallet.',
            'data' => [
                'wallet_id' => $wallet->id,
                'currency' => $wallet->currency,
                'balance' => $wallet->balance(),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $company = currentCompany();

        $entries = PaymentLedgerEntry::query()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = $this->ledgerRow($entry);
        }

        return response()->json([
            'code' => 'ok',
            'message' => 'Historique du wallet.',
            'data' => $rows,
        ]);
    }

    /**
     * Demande de retrait : l'instance est resolue pour le marche du tenant.
     */
    public function requestCashOut(Request $request): JsonResponse
    {
        $company = currentCompany();

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $currency = (string) ($validated['currency'] ?? $company->currency ?? 'DZD');

        try {
            $transaction = $this->payments->cashOut(
                companyId: (string) $company->id,
                amount: (int) $validated['amount'],
                currency: $currency,
                idempotencyKey: (string) $validated['idempotency_key'],
                country: $company->country ?? null,
            );
        } catch (ProviderNotResolvableException $exception) {
            return response()->json([
                'code' => 'provider_not_resolvable',
                'message' => $exception->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'code' => 'ok',
            'message' => 'Demande de retrait enregistree.',
            'data' => [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status->value,
                'amount' => $transaction->amount,
                'fee_amount' => $transaction->fee_amount,
                'currency' => $transaction->currency,
            ],
        ], 201);
    }

    /**
     * Commissions prelevees, visibles par le tenant.
     */
    public function fees(Request $request): JsonResponse
    {
        $company = currentCompany();

        $transactions = PaymentTransaction::query()
            ->where('company_id', $company->id)
            ->where('fee_amount', '>', 0)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $rows = [];

        foreach ($transactions as $transaction) {
            $rows[] = [
                'transaction_id' => $transaction->id,
                'type' => $transaction->type->value,
                'amount' => $transaction->amount,
                'fee_amount' => $transaction->fee_amount,
                'currency' => $transaction->currency,
                'created_at' => $transaction->created_at?->toIso8601String(),
            ];
        }

        return response()->json([
            'code' => 'ok',
            'message' => 'Commissions prelevees.',
            'data' => $rows,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ledgerRow(PaymentLedgerEntry $entry): array
    {
        return [
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

    /**
     * Marche du tenant et instance qui sera utilisee — information, pas choix.
     */
    public function market(Request $request): JsonResponse
    {
        $company = currentCompany();
        $currency = (string) $request->query('currency', $company->currency ?? 'DZD');

        $instance = $this->resolver->resolveOrNull(
            (string) ($company->country ?? 'DZ'),
            $currency,
            (string) $company->id,
        );

        return response()->json([
            'code' => 'ok',
            'message' => 'Marche du tenant.',
            'data' => [
                'country' => $company->country,
                'currency' => $currency,
                'provider_instance' => $instance?->toSafeArray(),
            ],
        ]);
    }
}
