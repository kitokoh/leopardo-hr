<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Interfaces\Api\V1\Controllers;

use App\Modules\Paiements\Application\Actions\RequestCashOutAction;
use App\Modules\Paiements\Application\Queries\WalletBalanceQuery;
use App\Modules\Paiements\Domain\Exceptions\ProviderNotResolvableException;
use App\Modules\Paiements\Domain\Exceptions\UnsupportedCurrencyException;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Modules\Paiements\Infrastructure\Services\PaymentCurrencyResolver;
use App\Modules\Paiements\Infrastructure\Services\ProviderResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        private readonly ProviderResolver $resolver,
        private readonly RequestCashOutAction $requestCashOut,
        private readonly WalletBalanceQuery $walletQuery,
        private readonly PaymentCurrencyResolver $currencies,
    ) {}

    public function balance(Request $request): JsonResponse
    {
        $company = currentCompany();

        $requested = $this->requestedCurrency($request);

        try {
            $currency = $this->currencies->resolve($requested, $company->currency, $company->country);
        } catch (UnsupportedCurrencyException $exception) {
            return $this->unsupportedCurrency($exception);
        }

        $wallet = $this->requestCashOut->tenantWallet((string) $company->id, $currency);

        $payload = $this->walletQuery->forWallet($wallet);
        // #8743 : rendre la resolution de devise observable plutot que devinee.
        $payload['currency_source'] = $this->currencies->sourceFor($requested, $company->currency);

        return response()->json([
            'code' => 'ok',
            'message' => 'Solde du wallet.',
            'data' => $payload,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $company = currentCompany();

        return response()->json([
            'code' => 'ok',
            'message' => 'Historique du wallet.',
            'data' => $this->walletQuery->history((string) $company->id),
        ]);
    }

    /**
     * Demande de retrait : l'instance est resolue pour le marche du tenant.
     */
    public function requestCashOut(Request $request): JsonResponse
    {
        $company = currentCompany();

        // #8743 : `size:3` acceptait n'importe quel trigramme, y compris une
        // devise inexistante. L'allowlist est desormais portee par le registre
        // unique `AccountingCurrencies`, jamais par une liste locale.
        /** @var array{amount: int, currency?: string|null, idempotency_key: string} $validated */
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', Rule::in($this->currencies->allowlist())],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $requested = isset($validated['currency']) && is_string($validated['currency'])
            ? $validated['currency']
            : null;

        try {
            $currency = $this->currencies->resolve($requested, $company->currency, $company->country);
        } catch (UnsupportedCurrencyException $exception) {
            return $this->unsupportedCurrency($exception);
        }

        try {
            $transaction = $this->requestCashOut->execute(
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
     * Marche du tenant et instance qui sera utilisee — information, pas choix.
     */
    public function market(Request $request): JsonResponse
    {
        $company = currentCompany();

        $requested = $this->requestedCurrency($request);

        try {
            $currency = $this->currencies->resolve($requested, $company->currency, $company->country);
        } catch (UnsupportedCurrencyException $exception) {
            return $this->unsupportedCurrency($exception);
        }

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
                'currency_source' => $this->currencies->sourceFor($requested, $company->currency),
                'provider_instance' => $instance?->toSafeArray(),
            ],
        ]);
    }

    /**
     * Devise demandee en query string, ou null. Le cast explicite evite de
     * laisser remonter un tableau (`?currency[]=`) vers le resolver.
     */
    private function requestedCurrency(Request $request): ?string
    {
        $value = $request->query('currency');

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * #8743 : refus explicite, jamais un repli silencieux.
     */
    private function unsupportedCurrency(UnsupportedCurrencyException $exception): JsonResponse
    {
        return response()->json([
            'code' => 'unsupported_currency',
            'message' => $exception->getMessage(),
            'data' => null,
        ], 422);
    }
}
