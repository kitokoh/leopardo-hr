<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Middleware\AI\AIRateLimiter;
use App\Modules\Billing\Domain\Models\AiCreditLedger;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Billing\Infrastructure\Services\AiCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Crédits IA achetables (#7764, spec MISSION_ESPACE_CLIENT §3.4).
 *
 * Couvre :
 *   1. achat sandbox : crédit immédiat idempotent + shape de la réponse ;
 *   2. webhook Stripe `checkout.session.completed` (mode payment) : crédite
 *      UNE seule fois — rejeu du même événement (registre #5444) ET événement
 *      distinct portant la même session (référence unique du ledger) = no-op ;
 *   3. décompte réel : quota du plan d'abord (compteur persistant), puis
 *      débit des crédits achetés, puis 422 AI_CREDITS_EXHAUSTED à sec
 *      (fail-closed) ;
 *   4. RBAC (principal only) et isolation multi-tenant.
 */
class AiCreditsTest extends TestCase
{
    use RefreshTenantDatabase;

    private const STRIPE_SECRET = 'whsec_test_stripe_2026';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::STRIPE_SECRET]);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function tenantWithPrincipal(string $plan = 'pilot'): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan,
            'status' => 'active',
            'payment_method' => 'stripe',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return [$company, $manager];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postStripeWebhook(array $payload): TestResponse
    {
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, self::STRIPE_SECRET);

        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function creditCheckoutEvent(string $eventId, string $sessionId, array $metadata): array
    {
        return [
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'mode' => 'payment',
                    'metadata' => $metadata,
                ],
            ],
        ];
    }

    private function rateLimit(Employee $employee): Response
    {
        $request = Request::create('/api/v1/ai/chat', 'POST');
        $request->setUserResolver(static fn (): Employee => $employee);

        /** @var AIRateLimiter $middleware */
        $middleware = app(AIRateLimiter::class);

        return $middleware->handle($request, static fn (): JsonResponse => new JsonResponse(['ok' => true]));
    }

    // ── Achat sandbox ────────────────────────────────────────────────────

    public function test_sandbox_checkout_credits_ledger_immediately(): void
    {
        config(['billing.sandbox_checkout' => true]);
        [$company, $manager] = $this->tenantWithPrincipal();
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/billing/ai-credits/checkout', [
            'pack' => 's',
            'success_url' => 'https://app.example/billing?ai_credits=success',
            'cancel_url' => 'https://app.example/billing?ai_credits=cancelled',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sandbox', true);

        $checkoutUrl = strval($response->json('data.checkout_url'));
        $this->assertStringContainsString('sandbox=1', $checkoutUrl);
        $this->assertStringContainsString('session_id=sandbox_ai_', $checkoutUrl);

        $this->assertSame(
            AiCreditService::PACKS['s']['tokens'],
            app(AiCreditService::class)->balance(strval($company->id)),
        );
        $this->assertSame(1, AiCreditLedger::query()->where('company_id', $company->id)->count());
    }

    public function test_sandbox_checkout_is_opt_in_and_falls_back_to_stripe_guard(): void
    {
        // Sandbox NON activé + Stripe non configuré → 503 explicite, jamais
        // de paiement simulé silencieux (#2628).
        config(['billing.sandbox_checkout' => false, 'services.stripe.secret' => null]);
        [, $manager] = $this->tenantWithPrincipal();
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/billing/ai-credits/checkout', [
            'pack' => 's',
            'success_url' => 'https://app.example/billing?ai_credits=success',
            'cancel_url' => 'https://app.example/billing?ai_credits=cancelled',
        ])->assertStatus(503)
            ->assertJsonPath('error', 'STRIPE_NOT_CONFIGURED');

        $this->assertSame(0, AiCreditLedger::query()->count());
    }

    public function test_checkout_rejects_unknown_pack(): void
    {
        config(['billing.sandbox_checkout' => true]);
        [, $manager] = $this->tenantWithPrincipal();
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/billing/ai-credits/checkout', [
            'pack' => 'xxl',
            'success_url' => 'https://app.example/billing',
            'cancel_url' => 'https://app.example/billing',
        ])->assertStatus(422);
    }

    // ── Webhook one-shot (idempotence) ───────────────────────────────────

    public function test_webhook_payment_checkout_credits_once_and_replay_is_noop(): void
    {
        [$company] = $this->tenantWithPrincipal();

        $event = $this->creditCheckoutEvent('evt_ai_1', 'cs_ai_123', [
            'purpose' => 'ai_credits',
            'company_id' => strval($company->id),
            'pack' => 's',
            'tokens' => '100000',
        ]);

        $this->postStripeWebhook($event)->assertOk();

        $this->assertSame(100_000, app(AiCreditService::class)->balance(strval($company->id)));
        $this->assertSame(1, AiCreditLedger::query()->where('company_id', $company->id)->count());

        // Rejeu du MÊME événement (redelivrance Stripe) → réponse mémorisée,
        // aucun second crédit (registre webhook #5444).
        $this->postStripeWebhook($event)->assertOk();
        $this->assertSame(1, AiCreditLedger::query()->where('company_id', $company->id)->count());

        // Événement DIFFÉRENT portant la même session (référence) → la
        // référence unique du ledger bloque le double crédit.
        $replayOtherEvent = $this->creditCheckoutEvent('evt_ai_2', 'cs_ai_123', [
            'purpose' => 'ai_credits',
            'company_id' => strval($company->id),
            'pack' => 's',
            'tokens' => '100000',
        ]);
        $this->postStripeWebhook($replayOtherEvent)->assertOk();

        $this->assertSame(100_000, app(AiCreditService::class)->balance(strval($company->id)));
        $this->assertSame(1, AiCreditLedger::query()->where('company_id', $company->id)->count());
    }

    public function test_webhook_payment_checkout_without_usable_metadata_is_ignored(): void
    {
        $event = $this->creditCheckoutEvent('evt_ai_3', 'cs_ai_456', [
            'purpose' => 'ai_credits',
            // company_id absent, tokens invalides.
            'tokens' => '0',
        ]);

        $this->postStripeWebhook($event)->assertOk();
        $this->assertSame(0, AiCreditLedger::query()->count());
    }

    // ── Décompte réel : quota du plan puis crédits, 422 à sec ───────────

    public function test_plan_quota_then_purchased_credits_then_fail_closed(): void
    {
        config(['ai.quotas.pilot' => 2]);
        [$company, $manager] = $this->tenantWithPrincipal('pilot');
        $companyId = strval($company->id);

        // 2 requêtes dans le quota du plan (compteur persistant en DB).
        $this->assertSame(200, $this->rateLimit($manager)->getStatusCode());
        $this->assertSame(200, $this->rateLimit($manager)->getStatusCode());
        $this->assertDatabaseHas('ai_usage_counters', [
            'company_id' => $companyId,
            'period' => now()->format('Y-m'),
            'used' => 2,
        ]);

        // Le compteur est PERSISTANT : un flush du cache ne réarme pas le quota.
        Cache::flush();

        // Quota épuisé, aucun crédit acheté → 422 fail-closed.
        $exhausted = $this->rateLimit($manager);
        $this->assertSame(422, $exhausted->getStatusCode());
        $decoded = (array) json_decode(strval($exhausted->getContent()), true);
        $this->assertSame('AI_CREDITS_EXHAUSTED', $decoded['error'] ?? null);
        $this->assertArrayHasKey('localized_message', $decoded);

        // Achat d'un forfait exact d'une requête → la requête suivante passe
        // en débitant le ledger.
        app(AiCreditService::class)->credit($companyId, AiCreditService::TOKENS_PER_REQUEST, 'cs_topup_1');
        $this->assertSame(200, $this->rateLimit($manager)->getStatusCode());
        $this->assertDatabaseHas('ai_credit_ledger', [
            'company_id' => $companyId,
            'delta' => -AiCreditService::TOKENS_PER_REQUEST,
            'reason' => AiCreditLedger::REASON_CONSUMPTION,
        ]);
        $this->assertSame(0, app(AiCreditService::class)->balance($companyId));

        // Solde de nouveau à sec → 422, et AUCUN débit fantôme persisté.
        $this->assertSame(422, $this->rateLimit($manager)->getStatusCode());
        $this->assertSame(0, app(AiCreditService::class)->balance($companyId));
    }

    public function test_enterprise_plan_is_unlimited(): void
    {
        config(['ai.quotas.enterprise' => null]);
        [, $manager] = $this->tenantWithPrincipal('enterprise');

        foreach (range(1, 5) as $i) {
            $this->assertSame(200, $this->rateLimit($manager)->getStatusCode());
        }

        $this->assertSame(0, AiCreditLedger::query()->count());
    }

    public function test_unknown_plan_falls_back_to_credits_fail_closed(): void
    {
        // Pas de souscription active → plan effectif `free` (EntitlementGuard
        // fail-closed) ; quota free à 0 → directement sur les crédits.
        config(['ai.quotas.free' => 0]);
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->assertSame(422, $this->rateLimit($manager)->getStatusCode());
    }

    // ── Lecture, RBAC et isolation ───────────────────────────────────────

    public function test_index_returns_balance_packs_and_paginated_history(): void
    {
        [$company, $manager] = $this->tenantWithPrincipal();
        $companyId = strval($company->id);
        $service = app(AiCreditService::class);
        $service->credit($companyId, 100_000, 'cs_hist_1');
        $service->debit($companyId, 1_000);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/billing/ai-credits?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.balance', 99_000)
            ->assertJsonPath('data.packs.0.code', 's')
            ->assertJsonPath('data.packs_version', AiCreditService::PACKS_VERSION)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonCount(1, 'data.history');
    }

    public function test_plain_employee_cannot_access_ai_credits(): void
    {
        [$company] = $this->tenantWithPrincipal();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/billing/ai-credits')->assertStatus(403);
        $this->postJson('/api/v1/billing/ai-credits/checkout', [
            'pack' => 's',
            'success_url' => 'https://app.example/billing',
            'cancel_url' => 'https://app.example/billing',
        ])->assertStatus(403);
    }

    public function test_credits_are_isolated_between_tenants(): void
    {
        [$companyA] = $this->tenantWithPrincipal();
        [, $managerB] = $this->tenantWithPrincipal();

        app(AiCreditService::class)->credit(strval($companyA->id), 100_000, 'cs_iso_1');

        Sanctum::actingAs($managerB);

        // Le tenant B ne voit ni le solde ni l'historique du tenant A.
        $this->getJson('/api/v1/billing/ai-credits')
            ->assertOk()
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('meta.total', 0);

        // Le solde de A est intact.
        $this->assertSame(100_000, app(AiCreditService::class)->balance(strval($companyA->id)));
    }
}
