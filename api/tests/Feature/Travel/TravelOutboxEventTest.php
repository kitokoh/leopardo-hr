<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use Illuminate\Database\QueryException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-211 (#6024) — Outbox transactionnelle TravelAgency.
 *
 * Structure identique au pattern `crm_outbox_events` (#5741) : idempotence
 * par tenant, réentrance, isolation cross-tenant.
 */
class TravelOutboxEventTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyB = $companyB;

        $this->tenants = app(TenantManager::class);
    }

    public function test_event_defaults_to_pending_status(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $event = TravelOutboxEvent::factory()->create();

            $this->assertSame(TravelOutboxEvent::STATUS_PENDING, $event->refresh()->status);
        });
    }

    public function test_idempotency_key_is_unique_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelOutboxEvent::factory()->create(['idempotency_key' => 'evt-key-001']);

            $this->expectException(QueryException::class);
            TravelOutboxEvent::factory()->create(['idempotency_key' => 'evt-key-001']);
        });
    }

    public function test_same_idempotency_key_allowed_across_tenants(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelOutboxEvent::factory()->create(['idempotency_key' => 'shared-evt-key']);
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            TravelOutboxEvent::factory()->create(['idempotency_key' => 'shared-evt-key']);
            $this->assertSame(1, TravelOutboxEvent::query()->count());
        });
    }

    public function test_events_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelOutboxEvent::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelOutboxEvent::query()->count());
        });
    }

    public function test_payload_redacted_is_cast_to_array(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $event = TravelOutboxEvent::factory()->create([
                'payload_redacted' => ['booking_reference' => 'GV-TEST0001'],
            ]);

            $this->assertIsArray($event->refresh()->payload_redacted);
            $this->assertSame('GV-TEST0001', $event->payload_redacted['booking_reference']);
        });
    }
}
