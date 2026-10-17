<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Interfaces\Api\V1\Controllers;

use App\Modules\Paiements\Domain\Exceptions\IdempotencyKeyRequiredException;
use App\Modules\Paiements\Domain\Exceptions\ProviderNotResolvableException;
use App\Modules\Paiements\Infrastructure\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BC-38 PAIEMENTS (#8689) — API interne service-a-service.
 *
 * Consommee par les autres BC (Paie, Cagnottes, VTC, Boutique) pour crediter,
 * debiter, transferer et rembourser. Authentification par jeton de service
 * (middleware dedie), **idempotence obligatoire**, reponses normalisees.
 *
 * Aucune fuite : ni secret, ni ID interne de fournisseur ne sortent d'ici.
 */
class InternalPaymentController
{
    public function __construct(private readonly PaymentService $payments) {}

    public function credit(Request $request): JsonResponse
    {
        return $this->run($request, fn (array $in): mixed => $this->payments->cashIn(
            companyId: $in['company_id'],
            amount: $in['amount'],
            currency: $in['currency'],
            idempotencyKey: $in['idempotency_key'],
            country: $in['country'] ?? null,
            metadata: $in['metadata'] ?? [],
        ));
    }

    public function debit(Request $request): JsonResponse
    {
        return $this->run($request, fn (array $in): mixed => $this->payments->cashOut(
            companyId: $in['company_id'],
            amount: $in['amount'],
            currency: $in['currency'],
            idempotencyKey: $in['idempotency_key'],
            country: $in['country'] ?? null,
            metadata: $in['metadata'] ?? [],
        ));
    }

    public function transfer(Request $request): JsonResponse
    {
        return $this->run($request, fn (array $in): mixed => $this->payments->internalTransfer(
            companyId: $in['company_id'],
            amount: $in['amount'],
            currency: $in['currency'],
            idempotencyKey: $in['idempotency_key'],
            fromWalletId: (int) $in['from_wallet_id'],
            toWalletId: (int) $in['to_wallet_id'],
            metadata: $in['metadata'] ?? [],
        ));
    }

    public function refund(Request $request): JsonResponse
    {
        return $this->run($request, fn (array $in): mixed => $this->payments->refund(
            companyId: $in['company_id'],
            amount: $in['amount'],
            currency: $in['currency'],
            idempotencyKey: $in['idempotency_key'],
            originalTransferGroupId: $in['transfer_group_id'],
            metadata: $in['metadata'] ?? [],
        ));
    }

    /**
     * @param  callable(array<string, mixed>): mixed  $handler
     */
    private function run(Request $request, callable $handler): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'],
            'from_wallet_id' => ['nullable', 'integer'],
            'to_wallet_id' => ['nullable', 'integer'],
            'transfer_group_id' => ['nullable', 'string', 'max:64'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $transaction = $handler($validated);
        } catch (IdempotencyKeyRequiredException $exception) {
            return $this->error('idempotency_key_required', $exception->getMessage(), 422);
        } catch (ProviderNotResolvableException $exception) {
            return $this->error('provider_not_resolvable', $exception->getMessage(), 422);
        }

        return response()->json([
            'code' => 'ok',
            'message' => 'Operation enregistree.',
            'data' => [
                'transaction_id' => $transaction->id,
                'type' => $transaction->type->value,
                'status' => $transaction->status->value,
                'amount' => $transaction->amount,
                'fee_amount' => $transaction->fee_amount,
                'currency' => $transaction->currency,
                'external_reference' => $transaction->external_reference,
            ],
        ], 201);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => null,
        ], $status);
    }
}
