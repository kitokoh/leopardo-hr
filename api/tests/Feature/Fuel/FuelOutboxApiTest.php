<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Contracts\FuelOutboxConsumer;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelOutboxConsumerRegistry;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Contrat Accounting FuelStation — FUEL-015 (issue #5809).
 *
 * Couvre : publication versionnée `fuel.sale.recorded.v1` à la vente,
 * déduplication (rejeu → pas de doublon), dispatch asynchrone (sent),
 * retry/backoff puis dead-letter après épuisement, rejeu manuel d'un
 * événement failed, isolation cross-tenant (l'outbox d'un tenant n'est pas
 * visible d'un autre).
 */
class FuelOutboxApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function station(Company $company, string $code = 'ST-01'): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => "Station {$code}",
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }

    public function test_sale_publishes_versioned_contract_event(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $this->station($this->companyA);

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'essence',
            'quantity' => 40.5,
            'unit_price' => 145.00,
            'external_id' => 'sale-001',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.amount', 5872.5);

        $this->assertDatabaseHas('fuel_outbox_events', [
            'company_id' => $this->companyA->id,
            'event_type' => FuelOutboxEvent::TYPE_SALE_RECORDED,
            'status' => FuelOutboxEvent::STATUS_PENDING,
        ]);

        $event = FuelOutboxEvent::query()
            ->where('company_id', $this->companyA->id)
            ->where('event_type', FuelOutboxEvent::TYPE_SALE_RECORDED)
            ->firstOrFail();

        $payload = $event->payload;
        $this->assertSame('essence', $payload['product']);
        $this->assertSame(5872.5, $payload['amount']);
        $this->assertSame('fuel_sale', $event->aggregate_type);
    }

    public function test_sale_replay_does_not_publish_duplicate_event(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $this->station($this->companyA);

        $payload = [
            'product' => 'gazole',
            'quantity' => 10,
            'unit_price' => 135.00,
            'external_id' => 'sale-002',
        ];

        $this->postJson('/api/v1/fuel-station/sales', $payload)->assertStatus(201);
        $this->postJson('/api/v1/fuel-station/sales', $payload)->assertStatus(201);

        $this->assertDatabaseCount('fuel_sales', 1);
        $this->assertDatabaseCount('fuel_outbox_events', 1);
    }

    public function test_dispatch_without_consumer_dead_letters_and_can_retry(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $this->station($this->companyA);

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'essence',
            'quantity' => 5,
            'unit_price' => 145.00,
            'external_id' => 'sale-003',
        ])->assertStatus(201);

        // Aucun consommateur enregistré (le module Accounting branchera le
        // sien) → dead-letter honnête, l'événement reste rejouable.
        $this->artisan('fuel:outbox-dispatch', ['--limit' => 10])
            ->expectsOutputToContain('1 événement(s) traité(s)')
            ->assertExitCode(0);

        $this->assertDatabaseHas('fuel_outbox_events', [
            'company_id' => $this->companyA->id,
            'event_type' => FuelOutboxEvent::TYPE_SALE_RECORDED,
            'status' => FuelOutboxEvent::STATUS_FAILED,
        ]);
    }

    public function test_dispatch_with_registered_consumer_marks_sent(): void
    {
        $registry = new FuelOutboxConsumerRegistry;
        $registry->register(new class implements FuelOutboxConsumer {
            public function supports(string $eventType): bool
            {
                return true;
            }

            /**
             * @param  array<string, mixed>  $payload
             */
            public function handle(array $payload): void
            {
                // Consommateur de test idempotent : ne fait rien.
            }
        });
        $this->app->instance(FuelOutboxConsumerRegistry::class, $registry);

        Sanctum::actingAs($this->manager($this->companyA));
        $this->station($this->companyA);

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'gazole',
            'quantity' => 20,
            'unit_price' => 135.00,
            'external_id' => 'sale-004',
        ])->assertStatus(201);

        $this->artisan('fuel:outbox-dispatch', ['--limit' => 10])
            ->expectsOutputToContain('1 événement(s) traité(s)')
            ->assertExitCode(0);

        $this->assertDatabaseHas('fuel_outbox_events', [
            'company_id' => $this->companyA->id,
            'event_type' => FuelOutboxEvent::TYPE_SALE_RECORDED,
            'status' => FuelOutboxEvent::STATUS_SENT,
        ]);
    }

    public function test_failed_event_can_be_retried_manually(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        /** @var FuelOutboxEvent $event */
        $event = FuelOutboxEvent::query()->create([
            'company_id' => $this->companyA->id,
            'event_type' => FuelOutboxEvent::TYPE_DELIVERY_RECEIVED,
            'aggregate_type' => 'fuel_delivery',
            'aggregate_id' => 1,
            'payload' => ['delivery_id' => 1],
            'status' => FuelOutboxEvent::STATUS_FAILED,
            'attempts' => 5,
            'available_at' => now()->subHour(),
            'last_error' => 'permanent: test',
            'idempotency_key' => 'retry-test-001',
        ]);

        $this->postJson("/api/v1/fuel-station/outbox/events/{$event->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.attempts', 0);

        // Rejouer un événement non-failed → 422.
        $event->refresh();
        $this->postJson("/api/v1/fuel-station/outbox/events/{$event->id}/retry")->assertStatus(422);
    }

    public function test_outbox_is_tenant_isolated(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        /** @var FuelOutboxEvent $eventB */
        $eventB = FuelOutboxEvent::query()->create([
            'company_id' => $this->companyB->id,
            'event_type' => FuelOutboxEvent::TYPE_SALE_RECORDED,
            'payload' => ['sale_id' => 999],
            'status' => FuelOutboxEvent::STATUS_FAILED,
            'attempts' => 1,
            'available_at' => now(),
            'last_error' => 'test',
            'idempotency_key' => 'tenant-b-001',
        ]);

        $this->getJson('/api/v1/fuel-station/outbox/events')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/fuel-station/outbox/events/{$eventB->id}/retry")->assertStatus(404);
    }
}
