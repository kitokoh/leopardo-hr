<?php

declare(strict_types=1);

namespace Tests\Feature\Fundraising;

use App\Modules\Fundraising\Domain\Enums\ContributionMethod;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use App\Modules\Fundraising\Infrastructure\Services\FundraisingGatewayFactory;
use App\Modules\Fundraising\Infrastructure\Services\MobileMoney\CinetPayGateway;
use App\Modules\Fundraising\Infrastructure\Services\MobileMoney\PayDunyaGateway;
use App\Modules\Fundraising\Infrastructure\Services\MobileMoney\PvitGateway;
use App\Modules\Fundraising\Infrastructure\Services\MobileMoneyGateway;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * FUND-101 — adaptateurs production mobile money (CinetPay / PayDunya /
 * PVIT) : initiation HTTP (Http::fake), webhooks fail-closed (#2614),
 * re-conciliation active, et résolution par la factory (provider inconnu
 * ⇒ exception, jamais de repli silencieux vers le sandbox).
 *
 * Aucun accès DB : la contribution est une instance en mémoire (les
 * adaptateurs ne lisent que ses attributs publics).
 */
final class MobileMoneyProviderAdaptersTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $config
     */
    private function factory(array $config): FundraisingGatewayFactory
    {
        return new FundraisingGatewayFactory(['mobile_money' => $config]);
    }

    private function contribution(): FundraisingContribution
    {
        return new FundraisingContribution([
            'reference' => 'FUND-TEST-101',
            'amount' => '5000',
            'currency' => 'XOF',
            'contributor_phone' => '+2250707070707',
        ]);
    }

    // ── Factory ─────────────────────────────────────────────────────

    public function test_factory_resolves_sandbox_by_default(): void
    {
        $gateway = $this->factory([])->forMethod(ContributionMethod::MOBILE_MONEY);

        $this->assertInstanceOf(MobileMoneyGateway::class, $gateway);
    }

    public function test_factory_resolves_configured_providers(): void
    {
        $this->assertInstanceOf(CinetPayGateway::class, $this->factory(['provider' => 'cinetpay'])->forMethod(ContributionMethod::MOBILE_MONEY));
        $this->assertInstanceOf(PayDunyaGateway::class, $this->factory(['provider' => 'paydunya'])->forMethod(ContributionMethod::MOBILE_MONEY));
        $this->assertInstanceOf(PvitGateway::class, $this->factory(['provider' => 'pvit'])->forMethod(ContributionMethod::MOBILE_MONEY));
    }

    public function test_factory_rejects_unknown_provider_fail_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->factory(['provider' => 'inconnu'])->forMethod(ContributionMethod::MOBILE_MONEY);
    }

    public function test_factory_resolves_explicit_webhook_providers(): void
    {
        $this->assertInstanceOf(CinetPayGateway::class, $this->factory([])->forProvider('cinetpay'));
        $this->assertInstanceOf(PayDunyaGateway::class, $this->factory([])->forProvider('paydunya'));
        $this->assertInstanceOf(PvitGateway::class, $this->factory([])->forProvider('pvit'));
    }

    // ── CinetPay ────────────────────────────────────────────────────

    public function test_cinetpay_initiate_returns_checkout_redirect(): void
    {
        Http::fake([
            'https://api-checkout.cinetpay.com/v2/payment' => Http::response([
                'code' => '201',
                'data' => ['payment_url' => 'https://checkout.cinetpay.test/pay/abc', 'payment_token' => 'tok_abc'],
            ], 200),
        ]);

        $gateway = new CinetPayGateway(['api_key' => 'key', 'site_id' => 'site']);
        $initiation = $gateway->initiate($this->contribution());

        $this->assertSame('FUND-TEST-101', $initiation->providerReference);
        $this->assertSame('https://checkout.cinetpay.test/pay/abc', $initiation->redirectUrl);
        $this->assertSame('pending', $initiation->status);

        Http::assertSent(fn ($request): bool => $request['transaction_id'] === 'FUND-TEST-101'
            && $request['apikey'] === 'key'
            && $request['site_id'] === 'site'
            && $request['channels'] === 'MOBILE_MONEY');
    }

    public function test_cinetpay_initiate_fails_closed_when_unconfigured(): void
    {
        $gateway = new CinetPayGateway([]);

        $this->assertFalse($gateway->isConfigured());

        try {
            $gateway->initiate($this->contribution());
            $this->fail('PAYMENT_GATEWAY_NOT_CONFIGURED attendu.');
        } catch (FundraisingException $exception) {
            $this->assertSame('PAYMENT_GATEWAY_NOT_CONFIGURED', $exception->errorCode());
        }
    }

    public function test_cinetpay_initiate_throws_on_provider_rejection(): void
    {
        Http::fake([
            'https://api-checkout.cinetpay.com/v2/payment' => Http::response(['code' => '600', 'message' => 'PAYMENT_FAILED'], 200),
        ]);

        $gateway = new CinetPayGateway(['api_key' => 'key', 'site_id' => 'site']);

        $this->expectException(RuntimeException::class);

        $gateway->initiate($this->contribution());
    }

    public function test_cinetpay_webhook_hmac_verified_and_mapped(): void
    {
        $gateway = new CinetPayGateway(['webhook_secret' => 'secret-cinetpay']);
        $payload = json_encode(['cpm_trans_id' => 'FUND-TEST-101', 'status' => 'ACCEPTED', 'payment_date' => '2026-10-13 04:00:00']);
        $signature = hash_hmac('sha256', (string) $payload, 'secret-cinetpay');

        $verified = $gateway->verifyWebhookSignature((string) $payload, $signature);

        $this->assertNotNull($verified);

        $update = $gateway->extractPayment($verified);

        $this->assertNotNull($update);
        $this->assertTrue($update->paid);
        $this->assertSame('FUND-TEST-101', $update->providerReference);
        $this->assertSame('2026-10-13 04:00:00', $update->paidAt);
    }

    public function test_cinetpay_webhook_rejected_without_secret_fail_closed(): void
    {
        $gateway = new CinetPayGateway([]);

        $this->assertNull($gateway->verifyWebhookSignature('{}', 'whatever'));
    }

    public function test_cinetpay_verify_reconciles_accepted_payment(): void
    {
        Http::fake([
            'https://api-checkout.cinetpay.com/v2/payment/check' => Http::response([
                'code' => '00',
                'data' => ['status' => 'ACCEPTED'],
            ], 200),
        ]);

        $gateway = new CinetPayGateway(['api_key' => 'key', 'site_id' => 'site']);
        $update = $gateway->verify('FUND-TEST-101');

        $this->assertNotNull($update);
        $this->assertTrue($update->paid);
        $this->assertSame('FUND-TEST-101', $update->providerReference);
    }

    // ── PayDunya ────────────────────────────────────────────────────

    public function test_paydunya_initiate_creates_invoice_with_keys(): void
    {
        Http::fake([
            'https://app.paydunya.com/api/v1/checkout-invoice/create' => Http::response([
                'response_code' => '00',
                'token' => 'invoice_tok_1',
                'response_text' => 'https://app.paydunya.test/checkout/invoice_tok_1',
            ], 200),
        ]);

        $gateway = new PayDunyaGateway(['master_key' => 'mk', 'private_key' => 'pk', 'token' => 'tk']);
        $initiation = $gateway->initiate($this->contribution());

        $this->assertSame('invoice_tok_1', $initiation->providerReference);
        $this->assertSame('https://app.paydunya.test/checkout/invoice_tok_1', $initiation->redirectUrl);

        Http::assertSent(fn ($request): bool => $request->hasHeader('PAYDUNYA-MASTER-KEY', 'mk')
            && $request->hasHeader('PAYDUNYA-PRIVATE-KEY', 'pk')
            && $request->hasHeader('PAYDUNYA-TOKEN', 'tk')
            && $request['custom_data']['contribution_reference'] === 'FUND-TEST-101');
    }

    public function test_paydunya_ipn_hash_sha512_verified_and_mapped(): void
    {
        $gateway = new PayDunyaGateway(['master_key' => 'mk', 'private_key' => 'pk', 'token' => 'tk']);
        $payload = json_encode([
            'data' => [
                'hash' => hash('sha512', 'mk'),
                'invoice_token' => 'invoice_tok_1',
                'status' => 'completed',
            ],
        ]);

        $verified = $gateway->verifyWebhookSignature((string) $payload, '');

        $this->assertNotNull($verified);

        $update = $gateway->extractPayment($verified);

        $this->assertNotNull($update);
        $this->assertTrue($update->paid);
        $this->assertSame('invoice_tok_1', $update->providerReference);
    }

    public function test_paydunya_ipn_rejected_on_bad_hash(): void
    {
        $gateway = new PayDunyaGateway(['master_key' => 'mk', 'private_key' => 'pk', 'token' => 'tk']);
        $payload = json_encode(['data' => ['hash' => hash('sha512', 'autre'), 'invoice_token' => 't', 'status' => 'completed']]);

        $this->assertNull($gateway->verifyWebhookSignature((string) $payload, ''));
    }

    public function test_paydunya_verify_reconciles_completed_invoice(): void
    {
        Http::fake([
            'https://app.paydunya.com/api/v1/checkout-invoice/confirm/*' => Http::response([
                'response_code' => '00',
                'data' => ['status' => 'completed'],
            ], 200),
        ]);

        $gateway = new PayDunyaGateway(['master_key' => 'mk', 'private_key' => 'pk', 'token' => 'tk']);
        $update = $gateway->verify('invoice_tok_1');

        $this->assertNotNull($update);
        $this->assertTrue($update->paid);
    }

    // ── PVIT ────────────────────────────────────────────────────────

    public function test_pvit_initiate_posts_payment_with_bearer(): void
    {
        Http::fake([
            'https://pvit.example.test/payments' => Http::response([
                'reference' => 'PVIT-1',
                'ussd_code' => '#144*1#',
                'status' => 'pending',
            ], 200),
        ]);

        $gateway = new PvitGateway(['base_uri' => 'https://pvit.example.test', 'merchant_tel' => '+24106000000', 'api_key' => 'pvit-key']);
        $initiation = $gateway->initiate($this->contribution());

        $this->assertSame('PVIT-1', $initiation->providerReference);
        $this->assertSame('#144*1#', $initiation->ussdCode);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer pvit-key')
            && $request['merchant_tel'] === '+24106000000'
            && $request['reference'] === 'FUND-TEST-101');
    }

    public function test_pvit_fails_closed_without_base_uri(): void
    {
        $gateway = new PvitGateway(['merchant_tel' => '+24106000000', 'api_key' => 'k']);

        $this->assertFalse($gateway->isConfigured());
        $this->assertNull($gateway->verify('PVIT-1'));
    }

    public function test_pvit_webhook_and_verify_roundtrip(): void
    {
        Http::fake([
            'https://pvit.example.test/payments/PVIT-1' => Http::response(['status' => 'paid'], 200),
        ]);

        $gateway = new PvitGateway([
            'base_uri' => 'https://pvit.example.test',
            'merchant_tel' => '+24106000000',
            'api_key' => 'k',
            'webhook_secret' => 'secret-pvit',
        ]);

        $payload = json_encode(['transaction_id' => 'evt_1', 'reference' => 'PVIT-1', 'status' => 'paid']);
        $signature = hash_hmac('sha256', (string) $payload, 'secret-pvit');

        $verified = $gateway->verifyWebhookSignature((string) $payload, $signature);
        $this->assertNotNull($verified);

        $update = $gateway->extractPayment($verified);
        $this->assertNotNull($update);
        $this->assertTrue($update->paid);
        $this->assertSame('PVIT-1', $update->providerReference);

        $reconciled = $gateway->verify('PVIT-1');
        $this->assertNotNull($reconciled);
        $this->assertTrue($reconciled->paid);
    }
}
