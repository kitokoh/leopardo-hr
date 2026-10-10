<?php

declare(strict_types=1);

namespace Tests\Unit\Fundraising;

use App\Modules\Fundraising\Domain\Enums\ContributionMethod;
use App\Modules\Fundraising\Domain\Enums\FundraiserStatus;
use App\Modules\Fundraising\Domain\Enums\PayoutStatus;
use App\Modules\Fundraising\Domain\Support\ReferenceGenerator;
use App\Modules\Fundraising\Infrastructure\Services\FundraisingGatewayFactory;
use App\Modules\Fundraising\Infrastructure\Services\FundraisingMoney;
use App\Modules\Fundraising\Infrastructure\Services\ManualGateway;
use App\Modules\Fundraising\Infrastructure\Services\MobileMoneyGateway;
use App\Modules\Fundraising\Infrastructure\Services\StripeContributionGateway;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Verticale FUNDRAISING — unitaires purs : générateurs, conversion
 * unités mineures, factory de passerelles, machines d'état des enums.
 */
class FundraisingSupportTest extends TestCase
{
    public function test_reference_generator_format_and_uniqueness(): void
    {
        $this->assertMatchesRegularExpression('/^FC-[A-Z0-9]{10}$/', ReferenceGenerator::contribution());
        $this->assertMatchesRegularExpression('/^FP-[A-Z0-9]{10}$/', ReferenceGenerator::payout());

        // Alphabet sans ambiguïté (pas de 0/O, 1/I).
        $reference = ReferenceGenerator::contribution();
        $this->assertStringNotContainsString('O', $reference);
        $this->assertStringNotContainsString('I', $reference);
        $this->assertStringNotContainsString('0', substr($reference, 3));
        $this->assertStringNotContainsString('1', substr($reference, 3));

        // 10k tirages sans collision (filet de sécurité du générateur).
        $seen = [];
        for ($i = 0; $i < 10000; $i++) {
            $seen[] = ReferenceGenerator::contribution();
        }
        $this->assertSame(count($seen), count(array_unique($seen)));
    }

    public function test_money_minor_units_with_zero_decimal_currencies(): void
    {
        // XOF/XAF : unité mineure = unité monétaire.
        $this->assertSame(5000, FundraisingMoney::toMinorUnits(5000.0, 'XOF'));
        $this->assertSame(5000, FundraisingMoney::toMinorUnits(5000.0, 'xof'));
        // EUR : centimes.
        $this->assertSame(500000, FundraisingMoney::toMinorUnits(5000.0, 'EUR'));
        $this->assertSame(5050, FundraisingMoney::toMinorUnits(50.5, 'EUR'));

        $this->assertTrue(FundraisingMoney::isZeroDecimal('XAF'));
        $this->assertFalse(FundraisingMoney::isZeroDecimal('DZD'));
    }

    public function test_gateway_factory_resolves_methods_and_providers(): void
    {
        $factory = new FundraisingGatewayFactory([
            'stripe' => ['secret_key' => '', 'webhook_secret' => ''],
            'mobile_money' => ['sandbox' => true],
        ]);

        $this->assertInstanceOf(StripeContributionGateway::class, $factory->forMethod(ContributionMethod::CARD));
        $this->assertInstanceOf(MobileMoneyGateway::class, $factory->forMethod(ContributionMethod::MOBILE_MONEY));
        $this->assertInstanceOf(ManualGateway::class, $factory->forMethod(ContributionMethod::CASH));
        $this->assertInstanceOf(ManualGateway::class, $factory->forMethod(ContributionMethod::BANK_TRANSFER));

        $this->assertSame('stripe', $factory->forProvider('stripe')->gatewayName());
        $this->assertSame('mobile_money', $factory->forProvider('mobile_money')->gatewayName());

        $this->expectException(InvalidArgumentException::class);
        $factory->forProvider('inconnu');
    }

    public function test_gateways_fail_closed_when_not_configured(): void
    {
        // Stripe sans clé → non configuré (initiation refusée en amont).
        $stripe = new StripeContributionGateway(['secret_key' => '']);
        $this->assertFalse($stripe->isConfigured());

        $stripeConfigured = new StripeContributionGateway(['secret_key' => 'sk_test_x']);
        $this->assertTrue($stripeConfigured->isConfigured());

        // Mobile money : sandbox toujours configuré ; production exige la clé.
        $this->assertTrue((new MobileMoneyGateway(['sandbox' => true]))->isConfigured());
        $this->assertFalse((new MobileMoneyGateway(['sandbox' => false, 'api_key' => '']))->isConfigured());
        $this->assertTrue((new MobileMoneyGateway(['sandbox' => false, 'api_key' => 'k']))->isConfigured());

        // Manuel : toujours configuré, jamais de webhook.
        $manual = new ManualGateway;
        $this->assertTrue($manual->isConfigured());
        $this->assertNull($manual->verifyWebhookSignature('{}', 'sig'));
        $this->assertNull($manual->verify('ref'));
    }

    public function test_mobile_money_webhook_signature_fail_closed(): void
    {
        $gateway = new MobileMoneyGateway(['sandbox' => false, 'api_key' => 'k', 'webhook_secret' => 's3cret']);

        $payload = '{"transaction_id":"t1","reference":"MM-1","status":"paid"}';
        $good = hash_hmac('sha256', $payload, 's3cret');

        $this->assertNotNull($gateway->verifyWebhookSignature($payload, $good));
        $this->assertNull($gateway->verifyWebhookSignature($payload, 'mauvaise'));

        // Secret absent → rejet (fail-closed).
        $noSecret = new MobileMoneyGateway(['sandbox' => true]);
        $this->assertNull($noSecret->verifyWebhookSignature($payload, $good));
    }

    public function test_enum_state_machines(): void
    {
        $this->assertTrue(FundraiserStatus::ACTIVE->acceptsContributions());
        $this->assertFalse(FundraiserStatus::PAUSED->acceptsContributions());
        $this->assertTrue(FundraiserStatus::CLOSED->isPubliclyVisible());
        $this->assertFalse(FundraiserStatus::DRAFT->isPubliclyVisible());
        $this->assertTrue(FundraiserStatus::COMPLETED->allowsPayout());
        $this->assertFalse(FundraiserStatus::CANCELLED->allowsPayout());

        $this->assertTrue(PayoutStatus::REQUESTED->locksBalance());
        $this->assertTrue(PayoutStatus::PAID->locksBalance());
        $this->assertFalse(PayoutStatus::FAILED->locksBalance());
        $this->assertFalse(PayoutStatus::CANCELLED->locksBalance());
    }
}
