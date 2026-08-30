<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Enums\PlanCode;
use App\Modules\Billing\Domain\Models\FeaturePlanMatrix;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Billing\Domain\Services\EntitlementGuard;
use App\Modules\Billing\Infrastructure\Services\StripeService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC21 (#6247) — garde d'entitlement : « un paiement ne débloque jamais
 * un module hors entitlement ».
 *
 * Les capabilities dérivent TOUJOURS de l'intersection { plan actif } ×
 * { matrice feature_plan_matrix }. Les chemins de paiement/webhook ne mutent
 * ni la matrice ni les features d'entreprise : un paiement ne peut donc
 * mécaniquement rien débloquer hors plan (fail-closed).
 */
class BillingEntitlementGuardTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Matrice canonique de test : `ai_assistant` réservé à enterprise,
        // `hr_automation` disponible dès pilot.
        FeaturePlanMatrix::create([
            'feature_key' => 'ai_assistant',
            'plan' => PlanCode::Pilot->value,
            'enabled' => false,
            'limit_value' => 0,
        ]);
        FeaturePlanMatrix::create([
            'feature_key' => 'ai_assistant',
            'plan' => PlanCode::Enterprise->value,
            'enabled' => true,
            'limit_value' => 500,
        ]);
        FeaturePlanMatrix::create([
            'feature_key' => 'hr_automation',
            'plan' => PlanCode::Pilot->value,
            'enabled' => true,
            'limit_value' => 100,
        ]);
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    private function subscription(Company $company, string $plan, string $status = 'active'): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan,
            'status' => $status,
            'payment_method' => 'stripe',
            'stripe_subscription_id' => 'sub_'.uniqid(),
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
        ]);

        return $subscription;
    }

    // ── Invariant central : un paiement ne débloque rien hors plan ──────────

    public function test_payment_webhook_does_not_unlock_out_of_plan_features(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, PlanCode::Pilot->value);
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'number' => 'LEO-'.uniqid(),
            'amount' => 29.00,
            'currency' => 'EUR',
            'total' => 29.00,
            'status' => 'sent',
            'due_date' => now()->addDays(10),
            'stripe_invoice_id' => 'in_ent',
        ]);

        $matrixBefore = FeaturePlanMatrix::query()->orderBy('id')->get(['feature_key', 'plan', 'enabled', 'limit_value'])->toArray();

        // Paiement reçu (webhook Stripe) — l'entreprise est sur le plan pilot.
        (new StripeService)->handleEvent([
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_ent',
                'amount_paid' => 2900,
                'currency' => 'eur',
                'charge' => 'ch_ent',
                'subscription' => $subscription->stripe_subscription_id,
            ]],
        ]);

        $matrixAfter = FeaturePlanMatrix::query()->orderBy('id')->get(['feature_key', 'plan', 'enabled', 'limit_value'])->toArray();

        // 1. Le paiement n'a touché à AUCUNE ligne de la matrice.
        self::assertSame($matrixBefore, $matrixAfter, 'le paiement ne mute pas la matrice feature_plan_matrix');

        // 2. `ai_assistant` (réservé enterprise) reste désactivé pour pilot.
        $guard = app(EntitlementGuard::class);
        self::assertFalse(
            $guard->isFeatureEnabled((string) $company->id, 'ai_assistant'),
            'un paiement ne débloque pas une feature hors entitlement du plan'
        );

        // 3. La feature du plan reste activée.
        self::assertTrue(
            $guard->isFeatureEnabled((string) $company->id, 'hr_automation'),
            'la feature incluse dans le plan reste activée'
        );

        // 4. La souscription a bien été confirmée active par le paiement.
        self::assertSame('active', $subscription->refresh()->status);
    }

    public function test_check_api_is_fail_closed_for_out_of_plan_feature(): void
    {
        $company = $this->company();
        $this->subscription($company, PlanCode::Pilot->value);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/feature-flags/check/ai_assistant')
            ->assertOk()
            ->assertJsonPath('data.plan', PlanCode::Pilot->value)
            ->assertJsonPath('data.enabled', false);

        $this->getJson('/api/v1/feature-flags/check/hr_automation')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.limit', 100);
    }

    // ── Fail-closed ─────────────────────────────────────────────────────────

    public function test_no_active_subscription_is_fail_closed_on_free_plan(): void
    {
        $company = $this->company();
        $guard = app(EntitlementGuard::class);

        self::assertSame(PlanCode::Free->value, $guard->planForCompany((string) $company->id));
        self::assertFalse($guard->isFeatureEnabled((string) $company->id, 'ai_assistant'));
    }

    public function test_expired_subscription_is_fail_closed(): void
    {
        $company = $this->company();
        $this->subscription($company, PlanCode::Enterprise->value, 'expired');
        $guard = app(EntitlementGuard::class);

        // Une souscription expirée n'est PAS une souscription active → free.
        self::assertSame(PlanCode::Free->value, $guard->planForCompany((string) $company->id));
        self::assertFalse($guard->isFeatureEnabled((string) $company->id, 'ai_assistant'));
    }

    // ── Downgrade ───────────────────────────────────────────────────────────

    public function test_downgrade_does_not_keep_higher_plan_features(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, PlanCode::Enterprise->value);
        $guard = app(EntitlementGuard::class);

        self::assertTrue($guard->isFeatureEnabled((string) $company->id, 'ai_assistant'));

        // Downgrade pilot : la feature enterprise n'est plus activée.
        $subscription->update(['plan' => PlanCode::Pilot->value]);

        self::assertFalse(
            $guard->isFeatureEnabled((string) $company->id, 'ai_assistant'),
            'un downgrade ne conserve pas les features du plan supérieur'
        );
    }

    // ── Intersection plan × matrice ─────────────────────────────────────────

    public function test_enabled_features_for_plan_is_matrix_intersection(): void
    {
        $guard = app(EntitlementGuard::class);

        self::assertSame(
            ['ai_assistant' => false, 'hr_automation' => true],
            $guard->enabledFeaturesForPlan(PlanCode::Pilot->value),
            'l\'intersection plan × matrice est exacte (y compris les features désactivées)'
        );
    }

    public function test_feature_limit_returns_limit_value(): void
    {
        $company = $this->company();
        $this->subscription($company, PlanCode::Enterprise->value);
        $guard = app(EntitlementGuard::class);

        self::assertSame(500, $guard->featureLimit((string) $company->id, 'ai_assistant'));
        self::assertNull($guard->featureLimit((string) $company->id, 'unknown_feature'));
    }
}
