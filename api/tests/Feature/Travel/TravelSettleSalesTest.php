<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use Illuminate\Support\Facades\Artisan;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-417 (#6069) — Synthèse ventes → Accounting.
 *
 * L'événement `travel.sales.settled.v1` est rejouable et cohérent : même
 * période = mêmes montants ; pas d'écriture dans les tables Accounting.
 */
class TravelSettleSalesTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;
        $this->tenants = app(TenantManager::class);
    }

    private function addPayment(PaymentStatus $status, int $amountMinor, ?string $createdAt = null): void
    {
        $this->tenants->withinTenant($this->company, function () use ($status, $amountMinor, $createdAt): void {
            $payment = TravelPayment::factory()->create([
                'amount_minor' => $amountMinor,
                'currency' => 'XAF',
                'status' => $status->value,
            ]);

            if ($createdAt !== null) {
                $payment->forceFill(['created_at' => $createdAt])->save();
            }
        });
    }

    public function test_settle_publishes_consistent_synthesis(): void
    {
        $this->addPayment(PaymentStatus::CONFIRMED, 10000);
        $this->addPayment(PaymentStatus::CONFIRMED, 20000);
        $this->addPayment(PaymentStatus::REFUNDED, 5000);

        Artisan::call('travel:settle-sales', [
            '--company' => (string) $this->company->id,
            '--period' => now()->format('Y-m'),
        ]);

        $event = TravelOutboxEvent::query()
            ->where('event_type', 'travel.sales.settled.v1')
            ->firstOrFail();

        self::assertSame(now()->format('Y-m'), $event->payload_redacted['period']);
        self::assertSame('XAF', $event->payload_redacted['currency']);
        self::assertSame(30000, $event->payload_redacted['confirmed_total_minor']);
        self::assertSame(5000, $event->payload_redacted['refunded_total_minor']);
        self::assertSame(2, $event->payload_redacted['confirmed_count']);
        self::assertSame(1, $event->payload_redacted['refunded_count']);
    }

    public function test_rerun_is_idempotent_when_amounts_unchanged(): void
    {
        $this->addPayment(PaymentStatus::CONFIRMED, 10000);

        Artisan::call('travel:settle-sales', [
            '--company' => (string) $this->company->id,
            '--period' => now()->format('Y-m'),
        ]);
        Artisan::call('travel:settle-sales', [
            '--company' => (string) $this->company->id,
            '--period' => now()->format('Y-m'),
        ]);

        self::assertSame(
            1,
            TravelOutboxEvent::query()->where('event_type', 'travel.sales.settled.v1')->count(),
            'même période + mêmes montants → aucune synthèse dupliquée',
        );
    }

    public function test_new_payment_on_same_period_updates_synthesis(): void
    {
        $this->addPayment(PaymentStatus::CONFIRMED, 10000);

        Artisan::call('travel:settle-sales', [
            '--company' => (string) $this->company->id,
            '--period' => now()->format('Y-m'),
        ]);

        $this->addPayment(PaymentStatus::CONFIRMED, 7000);

        Artisan::call('travel:settle-sales', [
            '--company' => (string) $this->company->id,
            '--period' => now()->format('Y-m'),
        ]);

        $events = TravelOutboxEvent::query()
            ->where('event_type', 'travel.sales.settled.v1')
            ->orderBy('id')
            ->get();

        self::assertCount(2, $events);
        self::assertSame(17000, $events->last()->payload_redacted['confirmed_total_minor']);
    }

    public function test_payments_outside_period_are_excluded(): void
    {
        $this->addPayment(PaymentStatus::CONFIRMED, 10000, now()->subMonths(3)->format('Y-m-d H:i:s'));

        Artisan::call('travel:settle-sales', [
            '--company' => (string) $this->company->id,
            '--period' => now()->format('Y-m'),
        ]);

        self::assertSame(0, TravelOutboxEvent::query()->count(), 'aucune synthèse pour une période sans paiement');
    }

    public function test_dry_run_does_not_publish(): void
    {
        $this->addPayment(PaymentStatus::CONFIRMED, 10000);

        Artisan::call('travel:settle-sales', [
            '--company' => (string) $this->company->id,
            '--period' => now()->format('Y-m'),
            '--dry-run' => true,
        ]);

        self::assertSame(0, TravelOutboxEvent::query()->count());
    }

    public function test_other_tenants_are_isolated(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->tenants->withinTenant($companyB, function (): void {
            TravelPayment::factory()->create([
                'amount_minor' => 999999,
                'currency' => 'XAF',
                'status' => PaymentStatus::CONFIRMED->value,
            ]);
        });

        Artisan::call('travel:settle-sales', [
            '--company' => (string) $this->company->id,
            '--period' => now()->format('Y-m'),
        ]);

        self::assertSame(0, TravelOutboxEvent::query()->count(), 'le tenant ciblé ne voit pas les paiements des autres');
    }
}
