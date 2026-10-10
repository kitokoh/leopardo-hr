<?php

declare(strict_types=1);

namespace Tests\Feature\Fundraising;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Fundraising\Domain\Enums\ContributionStatus;
use App\Modules\Fundraising\Domain\Enums\FundraiserStatus;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use App\Modules\Fundraising\Domain\Models\FundraisingPaymentEvent;
use App\Modules\Fundraising\Domain\Models\FundraisingPaymentRoute;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Verticale FUNDRAISING — webhooks providers (spec §4.3) : signature HMAC
 * fail-closed (secret absent ou signature invalide ⇒ 401), routage tenant
 * par annuaire, idempotence `(provider, event_id)` (double livraison sans
 * double crédit), événements inactionnables ⇒ 200 `ignored`.
 */
class FundraisingWebhookTest extends TestCase
{
    use RefreshTenantDatabase;

    private const STRIPE_SECRET = 'whsec_test_fundraising';

    private Company $company;

    private FundraisingContribution $contribution;

    protected function setUp(): void
    {
        parent::setUp();

        config(['fundraising.stripe.webhook_secret' => self::STRIPE_SECRET]);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $company->setFeature('fundraising', true);
        $company->save();
        $this->company = $company;

        $this->contribution = app(TenantManager::class)->withinTenant($company, function () use ($company): FundraisingContribution {
            /** @var Fundraiser $fundraiser */
            $fundraiser = Fundraiser::query()->create([
                'company_id' => $company->id,
                'slug' => 'aide-fatou-whk',
                'title' => 'Aide pour Fatou',
                'beneficiary_name' => 'Fatou Ndiaye',
                'currency' => 'XOF',
                'status' => FundraiserStatus::ACTIVE,
                'published_at' => now(),
            ]);

            /** @var FundraisingContribution $contribution */
            $contribution = FundraisingContribution::query()->create([
                'company_id' => $company->id,
                'fundraiser_id' => $fundraiser->id,
                'reference' => 'FC-WHKTEST123',
                'amount' => 5000,
                'currency' => 'XOF',
                'payment_method' => 'card',
                'provider' => 'stripe',
                'provider_reference' => 'cs_test_fundraising_1',
                'status' => ContributionStatus::PENDING,
            ]);

            return $contribution;
        });

        FundraisingPaymentRoute::query()->create([
            'provider' => 'stripe',
            'provider_reference' => 'cs_test_fundraising_1',
            'company_id' => $company->id,
            'contribution_reference' => 'FC-WHKTEST123',
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function signedStripePayload(string $eventId, string $sessionId, string $type = 'checkout.session.completed'): array
    {
        $payload = (string) json_encode([
            'id' => $eventId,
            'type' => $type,
            'data' => ['object' => ['id' => $sessionId, 'created' => time()]],
        ]);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, self::STRIPE_SECRET);

        return [$payload, 't='.$timestamp.',v1='.$signature];
    }

    public function test_valid_webhook_completes_contribution_and_credits_counter(): void
    {
        [$payload, $signature] = $this->signedStripePayload('evt_1', 'cs_test_fundraising_1');

        $this->call('POST', '/api/v1/webhooks/fundraising/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJsonPath('status', 'applied');

        [$contribution, $collected] = app(TenantManager::class)->withinTenant($this->company, function (): array {
            return [
                FundraisingContribution::query()->where('reference', 'FC-WHKTEST123')->firstOrFail(),
                (float) Fundraiser::query()->where('slug', 'aide-fatou-whk')->value('collected_amount'),
            ];
        });

        $this->assertSame(ContributionStatus::COMPLETED, $contribution->status);
        $this->assertNotNull($contribution->paid_at);
        $this->assertSame(5000.0, $collected);
    }

    public function test_double_delivery_is_not_credited_twice(): void
    {
        [$payload, $signature] = $this->signedStripePayload('evt_dup', 'cs_test_fundraising_1');

        $this->call('POST', '/api/v1/webhooks/fundraising/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJsonPath('status', 'applied');

        // Même événement re-livré → duplicate, aucun second crédit.
        $this->call('POST', '/api/v1/webhooks/fundraising/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJsonPath('status', 'duplicate');

        $collected = app(TenantManager::class)->withinTenant(
            $this->company,
            fn (): float => (float) Fundraiser::query()->where('slug', 'aide-fatou-whk')->value('collected_amount')
        );
        $this->assertSame(5000.0, $collected);

        // Un seul événement journalisé.
        $events = app(TenantManager::class)->withinTenant(
            $this->company,
            fn (): int => FundraisingPaymentEvent::query()->where('provider', 'stripe')->count()
        );
        $this->assertSame(1, $events);
    }

    public function test_invalid_signature_is_rejected_fail_closed(): void
    {
        [$payload] = $this->signedStripePayload('evt_bad', 'cs_test_fundraising_1');

        $this->call('POST', '/api/v1/webhooks/fundraising/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => 't='.time().',v1=deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(401)
            ->assertJsonPath('error', 'WEBHOOK_SIGNATURE_INVALID');

        // Secret absent ⇒ rejet également (fail-closed #2614).
        config(['fundraising.stripe.webhook_secret' => '']);
        [$payload2, $signature2] = $this->signedStripePayload('evt_nosecret', 'cs_test_fundraising_1');

        $this->call('POST', '/api/v1/webhooks/fundraising/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $signature2,
            'CONTENT_TYPE' => 'application/json',
        ], $payload2)->assertStatus(401);
    }

    public function test_unknown_provider_reference_is_ignored_with_200(): void
    {
        [$payload, $signature] = $this->signedStripePayload('evt_unknown', 'cs_test_inconnu');

        $this->call('POST', '/api/v1/webhooks/fundraising/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJsonPath('status', 'ignored');
    }

    public function test_unknown_provider_is_404(): void
    {
        $this->postJson('/api/v1/webhooks/fundraising/inconnu', [])->assertStatus(404);
    }

    public function test_mobile_money_webhook_hmac(): void
    {
        config(['fundraising.mobile_money.webhook_secret' => 'mm_secret']);
        config(['fundraising.mobile_money.sandbox' => false]);

        app(TenantManager::class)->withinTenant($this->company, function (): void {
            /** @var Fundraiser $fundraiser */
            $fundraiser = Fundraiser::query()->where('slug', 'aide-fatou-whk')->firstOrFail();

            FundraisingContribution::query()->create([
                'company_id' => $this->company->id,
                'fundraiser_id' => $fundraiser->id,
                'reference' => 'FC-MMTEST1234',
                'amount' => 2000,
                'currency' => 'XOF',
                'payment_method' => 'mobile_money',
                'provider' => 'mobile_money',
                'provider_reference' => 'MM-PROD123',
                'status' => ContributionStatus::PENDING,
            ]);
        });

        FundraisingPaymentRoute::query()->create([
            'provider' => 'mobile_money',
            'provider_reference' => 'MM-PROD123',
            'company_id' => $this->company->id,
            'contribution_reference' => 'FC-MMTEST1234',
        ]);

        $payload = (string) json_encode([
            'transaction_id' => 'txn_123',
            'reference' => 'MM-PROD123',
            'status' => 'paid',
        ]);
        $signature = hash_hmac('sha256', $payload, 'mm_secret');

        $this->call('POST', '/api/v1/webhooks/fundraising/mobile_money', [], [], [], [
            'HTTP_X-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJsonPath('status', 'applied');

        $status = app(TenantManager::class)->withinTenant(
            $this->company,
            fn (): ?string => FundraisingContribution::query()->where('reference', 'FC-MMTEST1234')->value('status')
        );
        $this->assertSame('completed', $status);
    }
}
