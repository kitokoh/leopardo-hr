<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Modules\RestaurantManager\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Exceptions\PaymentGatewayException;
use App\Modules\RestaurantManager\Domain\Payments\InitiatePaymentRequest;
use App\Modules\RestaurantManager\Domain\Payments\RefundRequest;
use App\Modules\RestaurantManager\Domain\Payments\VerifyPaymentRequest;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentGatewayRegistry;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * RESTO-406 (#6193) — Contrat PaymentGatewayInterface + registry + adapters
 * cash / carte / mobile money.
 *
 * Couvre : résolution des fournisseurs, fournisseur inconnu → erreur
 * normalisée (fail-closed), cycles initiate/verify/refund par adapter, aucun
 * secret en dur (les adapters ne lisent que la config).
 */
class RestaurantPaymentGatewayTest extends TestCase
{
    private function registry(): PaymentGatewayRegistry
    {
        return app(PaymentGatewayRegistry::class);
    }

    public function test_registry_resolves_all_v1_providers(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->has('cash'));
        $this->assertTrue($registry->has('card'));
        $this->assertTrue($registry->has('mobile_money'));
        $this->assertSame(['cash', 'card', 'mobile_money'], $registry->availableProviders());
    }

    public function test_registry_rejects_unknown_provider(): void
    {
        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionMessage('Unsupported payment provider "crypto".');

        $this->registry()->resolve('crypto');
    }

    public function test_cash_gateway_cycle(): void
    {
        $gateway = $this->registry()->resolve('cash');
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertSame('cash', $gateway->providerCode());

        $init = $gateway->initiate($this->request());
        $this->assertSame(PaymentStatus::CONFIRMED, $init->status);
        $this->assertStringStartsWith('CASH-', (string) $init->providerReference);

        $this->assertSame(PaymentStatus::CONFIRMED, $gateway->verify(new VerifyPaymentRequest(
            companyId: 'c1', providerReference: (string) $init->providerReference, amountMinor: 1000, currency: 'DZD',
        )));

        $refund = $gateway->refund(new RefundRequest(
            companyId: 'c1', providerReference: (string) $init->providerReference, amountMinor: 1000, currency: 'DZD', reasonCode: 'customer_request',
        ));
        $this->assertSame(PaymentStatus::REFUNDED, $refund->status);
    }

    public function test_card_gateway_cycle(): void
    {
        $gateway = $this->registry()->resolve('card');

        $init = $gateway->initiate($this->request());
        $this->assertSame(PaymentStatus::CONFIRMED, $init->status);
        $this->assertStringStartsWith('CARD-', (string) $init->providerReference);

        $this->assertSame(PaymentStatus::CONFIRMED, $gateway->verify(new VerifyPaymentRequest(
            companyId: 'c1', providerReference: (string) $init->providerReference, amountMinor: 1000, currency: 'DZD',
        )));
    }

    public function test_mobile_money_gateway_cycle_is_pending_until_callback(): void
    {
        $gateway = $this->registry()->resolve('mobile_money');

        $init = $gateway->initiate($this->request());
        $this->assertSame(PaymentStatus::PENDING, $init->status);
        $this->assertStringStartsWith('MM-', (string) $init->providerReference);

        // En sandbox, verify reste pending tant que le callback signé n'a pas
        // confirmé (le callback écrit directement le statut, RESTO-407).
        $this->assertSame(PaymentStatus::PENDING, $gateway->verify(new VerifyPaymentRequest(
            companyId: 'c1', providerReference: (string) $init->providerReference, amountMinor: 1000, currency: 'DZD',
        )));

        $refund = $gateway->refund(new RefundRequest(
            companyId: 'c1', providerReference: (string) $init->providerReference, amountMinor: 1000, currency: 'DZD', reasonCode: 'customer_request',
        ));
        $this->assertSame(PaymentStatus::PENDING, $refund->status);
    }

    private function request(): InitiatePaymentRequest
    {
        return new InitiatePaymentRequest(
            companyId: 'c1',
            amountMinor: 1000,
            currency: 'DZD',
            reference: 'RST-TEST1234',
            idempotencyKey: (string) Str::uuid(),
        );
    }
}
