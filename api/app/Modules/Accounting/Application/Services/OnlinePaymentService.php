<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Exceptions\DocumentNotSendableException;
use App\Modules\Accounting\Domain\Exceptions\PaymentAmountMismatchException;
use App\Modules\Accounting\Domain\Exceptions\WebhookSignatureInvalidException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Infrastructure\Services\ChargilyPaymentGateway;
use App\Modules\Accounting\Infrastructure\Services\GatewayMoney;
use App\Modules\Accounting\Infrastructure\Services\PaymentGatewayFactory;
use App\Modules\Accounting\Infrastructure\Services\PaymentRegistrationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * #5272 — Paiement en ligne des documents comptables (ADR-0017, option A).
 *
 * - createCheckout() : initie une session de paiement (Chargily DZ / Stripe)
 *   pour le solde restant d'un document émis, RBAC comptable/principal
 *   (portail client #5357 appellera le même endpoint derrière son propre
 *   mécanisme d'auth).
 * - handleWebhook() : rapprochement automatique — signature HMAC fail-closed,
 *   résolution du tenant par metadata, création idempotente d'un
 *   AccountingPayment (jamais de doublon au rejeu), anti-fraude montant ≠.
 *
 * DoD #5272 : un paiement en ligne pilote rapproché sans intervention.
 */
final class OnlinePaymentService
{
    /** Tolérance anti-fraude sur le montant notifié (unités mineures). */
    private const AMOUNT_TOLERANCE_MINOR = 2;

    public function __construct(
        private readonly PaymentGatewayFactory $factory,
        private readonly PaymentRegistrationService $payments,
        private readonly TenantManager $tenants,
    ) {}

    /**
     * Initie le checkout d'un document. Retourne l'URL de paiement hébergée,
     * sa date d'expiration et la passerelle routée.
     *
     * @return array{checkout_url: string, expires_at: string, gateway: string}
     */
    public function createCheckout(
        AccountingDocument $document,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $this->assertPayable($document);

        $company = currentCompany();
        $gateway = $this->factory->forCountry($company->country);

        $remaining = round((float) $document->total_ttc - (float) $document->paid_amount, 2);
        $checkout = $gateway->createCheckout($document, $remaining, $successUrl, $cancelUrl);

        return [
            'checkout_url' => $checkout->url,
            'expires_at' => $checkout->expiresAt->toIso8601String(),
            'gateway' => $checkout->gateway,
        ];
    }

    /**
     * Traite un webhook passerelle (public, signature HMAC).
     *
     * @return string processed | replayed | ignored
     */
    public function handleWebhook(string $gatewayName, string $payload, string $signatureHeader): string
    {
        $gateway = $this->factory->byName($gatewayName);

        if ($gateway === null) {
            Log::warning('Accounting webhook: unknown gateway', ['gateway' => $gatewayName]);

            throw new WebhookSignatureInvalidException();
        }

        $data = $gateway->verifyWebhookSignature($payload, $signatureHeader);
        if ($data === null) {
            throw new WebhookSignatureInvalidException();
        }

        $payment = $gateway->extractPayment($data);
        if ($payment === null || $payment->eventType === 'other') {
            // Événement hors périmètre (webhook de test, ping…) : rien à faire.
            return 'ignored';
        }

        if ($payment->eventType === 'cancelled') {
            // US3 : paiement annulé/expiré → document inchangé, aucun paiement fantôme.
            Log::info('Accounting webhook: checkout cancelled, document unchanged', [
                'gateway' => $gatewayName,
                'gateway_payment_id' => $payment->gatewayPaymentId,
            ]);

            return 'ignored';
        }

        if ($payment->documentId === null || $payment->companyId === null) {
            Log::warning('Accounting webhook: payment without document/company metadata — ignoré', [
                'gateway' => $gatewayName,
                'gateway_payment_id' => $payment->gatewayPaymentId,
            ]);

            return 'ignored';
        }

        /** @var Company|null $company */
        $company = Company::query()->find($payment->companyId);
        if ($company === null) {
            Log::warning('Accounting webhook: company introuvable — ignoré', [
                'company_id' => $payment->companyId,
                'gateway_payment_id' => $payment->gatewayPaymentId,
            ]);

            return 'ignored';
        }

        return $this->tenants->withinTenant($company, function () use ($payment): string {
            // Idempotence : le gateway_payment_id est déjà rapproché → rejeu
            // sans doublon (200 rejoué, aucun nouveau paiement).
            $existing = AccountingPayment::query()
                ->where('gateway_payment_id', $payment->gatewayPaymentId)
                ->first();

            if ($existing !== null) {
                Log::info('Accounting webhook: paiement déjà rapproché — rejeu sans doublon', [
                    'gateway_payment_id' => $payment->gatewayPaymentId,
                    'payment_id' => $existing->id,
                ]);

                return 'replayed';
            }

            /** @var AccountingDocument|null $document */
            $document = AccountingDocument::query()->find($payment->documentId);
            if ($document === null) {
                Log::warning('Accounting webhook: document introuvable — ignoré', [
                    'document_id' => $payment->documentId,
                    'gateway_payment_id' => $payment->gatewayPaymentId,
                ]);

                return 'ignored';
            }

            // Anti-fraude montant ≠ : le montant notifié ne doit JAMAIS dépasser
            // le solde restant du document (tolérance 2 unités mineures). Un
            // montant inférieur est un paiement partiel légitime (US2.5 →
            // partially_paid) ; un montant supérieur est refusé sans
            // rapprochement (US2.4).
            $currency = strtoupper($payment->currency);
            $remaining = round((float) $document->total_ttc - (float) $document->paid_amount, 2);
            $expectedMinor = GatewayMoney::toMinorUnits($remaining, $currency);

            if ($payment->amountMinor > $expectedMinor + self::AMOUNT_TOLERANCE_MINOR) {
                throw new PaymentAmountMismatchException(
                    (float) $document->total_ttc,
                    (float) $document->paid_amount,
                    $payment->amountMinor
                );
            }

            $amount = GatewayMoney::fromMinorUnits($payment->amountMinor, $currency);

            // Rapprochement automatique : enregistrement (recorded) + match
            // immédiat (matched) — « sans intervention », DoD #5272. La règle
            // « jamais payé > total » du registre s'applique en profondeur.
            $recorded = $this->payments->register(
                document: $document,
                amount: $amount,
                method: $payment->method,
                reference: $payment->gatewayPaymentId,
                receivedAt: Carbon::now(),
            );
            $this->payments->reconcile($recorded);

            Log::info('Accounting webhook: paiement en ligne rapproché automatiquement', [
                'gateway_payment_id' => $payment->gatewayPaymentId,
                'payment_id' => $recorded->id,
                'document_id' => $document->id,
                'amount' => $amount,
                'method' => $payment->method,
            ]);

            return 'processed';
        });
    }

    private function assertPayable(AccountingDocument $document): void
    {
        $payable = [
            DocumentStatus::Sent->value,
            DocumentStatus::PartiallyPaid->value,
            DocumentStatus::Overdue->value,
        ];

        if (! in_array($document->status, $payable, true)) {
            throw new DocumentNotSendableException((string) $document->status);
        }

        $remaining = round((float) $document->total_ttc - (float) $document->paid_amount, 2);
        if ($remaining <= 0) {
            throw new DocumentNotSendableException((string) $document->status);
        }
    }
}
