<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\PayOrderAction;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentCallbackSigner;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantOrderPaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * RESTO-407 (#6194) — Callback signé de confirmation de paiement.
 *
 * `POST /restaurant/payments/{payment}/callback` — route PUBLIQUE (hors
 * auth Sanctum) : la confiance est portée par la signature HMAC-SHA256 de
 * l'en-tête `X-Leopardo-Signature` (fail-closed) calculée avec le secret du
 * tenant porté par le payload (spec §6.2).
 *
 * Idempotence : rejouer le même callback (même payload signé) → 1 seul
 * paiement confirmé (le statut confirmé est retourné tel quel). Montant
 * vérifié (amount_mismatch → 422) ; payload redigé stocké
 * (`callback_payload_redacted`, aucune PII). Le tenant est résolu depuis le
 * payload puis posé via TenantManager::withinTenant (pattern
 * AccountingPaymentWebhookController #5272).
 */
final class RestaurantPaymentCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentCallbackSigner $signer,
        private readonly TenantManager $tenants,
        private readonly PayOrderAction $payAction,
    ) {
    }

    public function handle(int $payment, Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('X-Leopardo-Signature', '');

        $data = json_decode($payload, true);
        $companyId = is_array($data) && isset($data['company_id']) && is_string($data['company_id'])
            ? $data['company_id']
            : null;

        if ($companyId === null) {
            return new JsonResponse(['error' => 'invalid_payload'], 422);
        }

        if (! $this->signer->verify($payload, $signature, $companyId)) {
            Log::warning('Restaurant payment callback: invalid HMAC signature', [
                'payment' => $payment,
                'company_id' => $companyId,
            ]);

            return new JsonResponse(['error' => 'invalid_signature'], 401);
        }

        /** @var Company|null $company */
        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            return new JsonResponse(['error' => 'company_not_found'], 404);
        }

        $result = $this->tenants->withinTenant($company, function () use ($payment, $data): ?RestaurantOrderPayment {
            $paymentRow = RestaurantOrderPayment::query()->find($payment);

            if (! $paymentRow instanceof RestaurantOrderPayment || $paymentRow->company_id !== $company->id) {
                return null;
            }

            // Montant et référence vérifiés (le payload est signé, mais on
            // ne fait jamais confiance aux valeurs du callback seules).
            $callbackReference = $data['provider_reference'] ?? null;
            $callbackAmount = $data['amount_minor'] ?? null;

            if (! is_string($callbackReference) || $callbackReference !== $paymentRow->provider_reference) {
                return $this->redactAndFail($paymentRow, $data, 'reference_mismatch');
            }

            if (! is_int($callbackAmount) || $callbackAmount !== $paymentRow->amount_minor) {
                return $this->redactAndFail($paymentRow, $data, 'amount_mismatch');
            }

            $callbackStatus = $data['status'] ?? 'confirmed';

            if ($callbackStatus === 'failed') {
                $paymentRow->forceFill([
                    'status' => PaymentStatus::FAILED->value,
                    'callback_payload_redacted' => $this->redact($data),
                ])->save();

                return $paymentRow;
            }

            if ($paymentRow->status !== PaymentStatus::CONFIRMED) {
                $this->payAction->confirmPayment($paymentRow, $paymentRow->provider_reference);
                $paymentRow->forceFill([
                    'callback_payload_redacted' => $this->redact($data),
                ])->save();
            }

            $paymentRow->refresh();

            return $paymentRow;
        });

        if (! $result instanceof RestaurantOrderPayment) {
            return new JsonResponse(['error' => 'payment_not_found'], 404);
        }

        return (new RestaurantOrderPaymentResource($result))->response();
    }

    private function redactAndFail(RestaurantOrderPayment $payment, array $data, string $reason): RestaurantOrderPayment
    {
        Log::warning('Restaurant payment callback: mismatch', [
            'payment' => $payment->id,
            'reason' => $reason,
        ]);

        $payment->forceFill([
            'status' => PaymentStatus::FAILED->value,
            'callback_payload_redacted' => $this->redact($data),
        ])->save();

        $payment->refresh();

        return $payment;
    }

    /**
     * Redige le payload : seules les clés sûres (non-PII, non-secret) sont
     * conservées — jamais de carte, téléphone client, token, etc.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        $allowed = ['provider_reference', 'amount_minor', 'currency', 'status', 'company_id'];

        $redacted = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $redacted[$key] = $data[$key];
            }
        }

        $redacted['received_at'] = now()->toIso8601String();

        return $redacted;
    }
}
