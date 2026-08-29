<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC21 (BILLING, #5897) — politique explicite de recouvrement.
 *
 * Machine à états des souscriptions (SubscriptionStatus + transitionTo) et
 * commande `billing:enforce-delinquency` (backlog BC-21 : « un tenant en
 * défaut doit être traité selon une politique explicite »).
 */
class BillingDelinquencyPolicyTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    private function subscription(Company $company, string $status, ?string $periodEnd = null): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => $status,
            'payment_method' => 'stripe',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => $periodEnd ?? now()->addMonth(),
        ]);

        return $subscription;
    }

    // ── Machine à états ───────────────────────────────────────────────────────

    public function test_transition_matrix_is_enforced(): void
    {
        self::assertTrue(SubscriptionStatus::Active->canTransitionTo(SubscriptionStatus::PastDue));
        self::assertTrue(SubscriptionStatus::Active->canTransitionTo(SubscriptionStatus::Cancelled));
        self::assertTrue(SubscriptionStatus::PastDue->canTransitionTo(SubscriptionStatus::Expired));
        self::assertTrue(SubscriptionStatus::Expired->canTransitionTo(SubscriptionStatus::Active));
        self::assertFalse(SubscriptionStatus::Expired->canTransitionTo(SubscriptionStatus::PastDue));
        self::assertFalse(SubscriptionStatus::Cancelled->canTransitionTo(SubscriptionStatus::Active));
        self::assertTrue(SubscriptionStatus::Cancelled->isTerminal());
    }

    public function test_invalid_transition_throws(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, SubscriptionStatus::Cancelled->value);

        try {
            $subscription->transitionTo(SubscriptionStatus::Active);
            self::fail('une transition depuis cancelled doit être refusée');
        } catch (InvalidArgumentException) {
            self::assertSame(SubscriptionStatus::Cancelled->value, $subscription->status);
        }
    }

    public function test_recovery_from_expired_to_active_is_allowed(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, SubscriptionStatus::Expired->value);

        $subscription->transitionTo(SubscriptionStatus::Active, ['current_period_end' => now()->addMonth()]);

        self::assertSame(SubscriptionStatus::Active->value, $subscription->status);
    }

    // ── Politique de recouvrement (commande) ─────────────────────────────────

    public function test_expired_active_period_becomes_past_due(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, SubscriptionStatus::Active->value, now()->subDay()->toDateTimeString());

        Artisan::call('billing:enforce-delinquency');

        self::assertSame(
            SubscriptionStatus::PastDue->value,
            $subscription->refresh()->status,
            'une période expirée sans paiement → past_due (politique explicite)'
        );
    }

    public function test_past_due_beyond_grace_period_is_expired(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, SubscriptionStatus::PastDue->value, now()->subDays(10)->toDateTimeString());

        Artisan::call('billing:enforce-delinquency', ['--grace-days' => 7]);

        self::assertSame(
            SubscriptionStatus::Expired->value,
            $subscription->refresh()->status,
            'past_due hors délai de grâce (7 j) → expired'
        );
    }

    public function test_past_due_within_grace_period_stays_past_due(): void
    {
        $company = $this->company();
        $subscription = $this->subscription($company, SubscriptionStatus::PastDue->value, now()->subDays(3)->toDateTimeString());

        Artisan::call('billing:enforce-delinquency', ['--grace-days' => 7]);

        self::assertSame(
            SubscriptionStatus::PastDue->value,
            $subscription->refresh()->status,
            'past_due dans le délai de grâce : pas de suspension'
        );
    }

    public function test_command_is_replayable_idempotent(): void
    {
        $company = $this->company();
        $this->subscription($company, SubscriptionStatus::Expired->value, now()->subDays(30)->toDateTimeString());

        Artisan::call('billing:enforce-delinquency');
        Artisan::call('billing:enforce-delinquency');

        self::assertSame(
            1,
            Subscription::query()->where('status', SubscriptionStatus::Expired->value)->count(),
            'la commande est idempotente (rejouable sans effet de bord)'
        );
    }
}
