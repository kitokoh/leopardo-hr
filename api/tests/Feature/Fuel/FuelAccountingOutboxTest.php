<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;
use App\Core\Auth\Domain\Models\AuditLog;use App\Modules\FuelStation\Domain\Contracts\FuelOutboxConsumer;use App\Modules\FuelStation\Domain\Exceptions\PermanentFuelOutboxException;use App\Modules\FuelStation\Domain\Exceptions\TransientFuelOutboxException;use App\Modules\FuelStation\Infrastructure\Services\FuelOutboxConsumerRegistry;use App\Modules\FuelStation\Infrastructure\Services\FuelOutboxPublisher;use Illuminate\Foundation\Testing\WithFaker;use Illuminate\Support\Facades\Artisan;
use App\Modules\FuelStation\Domain\Contracts\FuelOutboxConsumer;use App\Modules\FuelStation\Domain\Exceptions\PermanentFuelOutboxException;use App\Modules\FuelStation\Domain\Exceptions\TransientFuelOutboxException;use App\Modules\FuelStation\Infrastructure\Services\FuelOutboxConsumerRegistry;use App\Modules\FuelStation\Infrastructure\Services\FuelOutboxPublisher;use Illuminate\Foundation\Testing\WithFaker;use Illuminate\Support\Facades\Artisan;

/**
 * Contrat Accounting — FUEL-015 (issue #5809).
 *
 * Couvre : événements versionnés publiés dans l'outbox (sale.recorded,
 * cash_session.closed, stock.reconciled), agrégats validés, consommation
 * par fuel:outbox-dispatch (sent, sans PII), idempotence de publication.
 */
class FuelAccountingOutboxTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    public function test_sale_records_outbox_event(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => 10,
            'unit_price' => 150,
            'external_id' => 'ACC-0001',
        ])->assertStatus(200);

        $event = DB::table('fuel_outbox_events')
            ->where('company_id', $company->id)
            ->where('event_type', FuelOutboxEvent::EVENT_SALE_RECORDED)
            ->first();

        $this->assertNotNull($event);
        $payload = json_decode((string) $event->payload, true);
        $this->assertIsArray($payload);
        $this->assertSame(1500.0, (float) ($payload['amount'] ?? 0));
        $this->assertArrayNotHasKey('employee_id', $payload); // pas de PII
    }

    public function test_cash_session_close_publishes_closed_event(): void
    {
        $company = $this->company();
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        /** @var FuelCashSession $session */
        $session = FuelCashSession::query()->create([
            'company_id' => $company->id,
            'opened_by' => $operator->id,
            'opening_balance' => 100,
            'status' => FuelCashSession::STATUS_OPEN,
        ]);

        $this->postJson('/api/v1/fuel-station/cash-sessions/'.$session->id.'/close', [
            'closing_balance' => 500,
        ])->assertStatus(200);

        $event = DB::table('fuel_outbox_events')
            ->where('company_id', $company->id)
            ->where('event_type', FuelOutboxEvent::EVENT_CASH_SESSION_CLOSED)
            ->first();

        $this->assertNotNull($event);
    }

    public function test_outbox_dispatch_consumes_and_marks_sent(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Gazole',
            'quantity' => 30,
            'unit_price' => 140,
            'external_id' => 'ACC-0002',
        ])->assertStatus(200);

        $this->artisan('fuel:outbox-dispatch', ['--limit' => 10])
            ->assertExitCode(0);

        $event = DB::table('fuel_outbox_events')
            ->where('company_id', $company->id)
            ->where('event_type', FuelOutboxEvent::EVENT_SALE_RECORDED)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(FuelOutboxEvent::STATUS_SENT, $event->status);
        $this->assertNotNull($event->processed_at);
    }

    public function test_outbox_publish_is_idempotent(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        // Deux ventes distinctes → deux événements (un par agrégat).
        $this->postJson('/api/v1/fuel-station/sales', ['product' => 'A', 'quantity' => 1, 'unit_price' => 10, 'external_id' => 'I1'])->assertStatus(200);
        $this->postJson('/api/v1/fuel-station/sales', ['product' => 'B', 'quantity' => 2, 'unit_price' => 20, 'external_id' => 'I2'])->assertStatus(200);

        $count = FuelOutboxEvent::query()
            ->where('company_id', $company->id)
            ->where('event_type', FuelOutboxEvent::EVENT_SALE_RECORDED)
            ->count();

        $this->assertSame(2, $count);
    }


    public function test_cash_session_close_publishes_aggregate_to_outbox(): void
    {
        [$company, $manager, $session] = $this->seedClosedSession();

        $this->assertDatabaseHas('fuel_outbox_events', [
            'company_id' => $company->id,
            'event_type' => 'fuel.cash.closed.v1',
            'status' => 'pending',
        ]);

        $event = FuelOutboxEvent::query()->where('event_type', 'fuel.cash.closed.v1')->firstOrFail();
        $this->assertSame('fuel_cash_session', $event->aggregate_type);
        $this->assertSame((string) $session->id, $event->aggregate_id);
        $this->assertSame('1.0', $event->payload['schema_version'] ?? null);
        $this->assertSame('cash.closed', $event->payload['event'] ?? null);
        $this->assertArrayHasKey('aggregate', $event->payload);
        $this->assertSame(500.0, (float) ($event->payload['aggregate']['variance'] ?? 0));
    }


    public function test_publish_is_idempotent_by_key(): void
    {
        [$company] = $this->seedTenant();
        $outbox = app(FuelOutboxPublisher::class);

        $payload = [
            'schema_version' => '1.0',
            'event' => 'shift.closed',
            'company_id' => $company->id,
            'idempotency_key' => 'fuel.shift.closed.v1:99',
            'aggregate' => ['shift_id' => 99],
        ];

        $first = $outbox->publish(
            companyId: $company->id,
            eventType: 'fuel.shift.closed.v1',
            payload: $payload,
            aggregateType: 'fuel_shift',
            aggregateId: '99',
        );

        $second = $outbox->publish(
            companyId: $company->id,
            eventType: 'fuel.shift.closed.v1',
            payload: $payload,
            aggregateType: 'fuel_shift',
            aggregateId: '99',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, FuelOutboxEvent::query()->count());
    }


    public function test_dispatch_consumes_and_records_idempotent_audit(): void
    {
        [$company, $manager, $session] = $this->seedClosedSession();

        Artisan::call('fuel:outbox-dispatch', ['--limit' => 100]);

        $event = FuelOutboxEvent::query()->where('event_type', 'fuel.cash.closed.v1')->firstOrFail();
        $this->assertSame('sent', $event->status);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'fuel.accounting.cash.closed',
        ]);

        // Rejeu : le consommateur est idempotent (aucune trace dupliquée).
        Artisan::call('fuel:outbox-dispatch', ['--limit' => 100]);

        $this->assertSame(1, AuditLog::query()
            ->where('company_id', $company->id)
            ->where('action', 'fuel.accounting.cash.closed')
            ->count());
    }


    public function test_unknown_event_type_dead_letters(): void
    {
        [$company] = $this->seedTenant();
        $outbox = app(FuelOutboxPublisher::class);

        $outbox->publish(
            companyId: $company->id,
            eventType: 'fuel.unknown.v1',
            payload: ['schema_version' => '1.0', 'event' => 'unknown', 'company_id' => $company->id],
        );

        Artisan::call('fuel:outbox-dispatch', ['--limit' => 100]);

        $event = FuelOutboxEvent::query()->where('event_type', 'fuel.unknown.v1')->firstOrFail();
        $this->assertSame('failed', $event->status);
        $this->assertStringContainsString('no_consumer', (string) $event->last_error);
    }


    public function test_transient_error_retries_with_backoff_then_succeeds(): void
    {
        [$company] = $this->seedTenant();
        $outbox = app(FuelOutboxPublisher::class);

        $outbox->publish(
            companyId: $company->id,
            eventType: 'fuel.flaky.v1',
            payload: ['schema_version' => '1.0', 'event' => 'flaky', 'company_id' => $company->id],
        );

        // Un consommateur flaky : échoue transitoirement au premier passage.
        $registry = app(FuelOutboxConsumerRegistry::class);
        $registry->register(new class implements FuelOutboxConsumer
        {
            private int $calls = 0;

            public function supports(string $eventType): bool
            {
                return $eventType === 'fuel.flaky.v1';
            }

            /** @param  array<string, mixed>  $payload */
            public function handle(array $payload): void
            {
                $this->calls++;
                if ($this->calls === 1) {
                    throw new TransientFuelOutboxException('downstream 503');
                }
            }
        });

        Artisan::call('fuel:outbox-dispatch', ['--limit' => 100]);

        $event = FuelOutboxEvent::query()->where('event_type', 'fuel.flaky.v1')->firstOrFail();
        $this->assertSame('pending', $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertNotNull($event->available_at);
        $this->assertTrue($event->available_at->isFuture());
        $this->assertStringContainsString('downstream 503', (string) $event->last_error);

        // Le second passage (après backoff) réussit.
        $event->forceFill(['available_at' => now()->subMinute()])->save();
        Artisan::call('fuel:outbox-dispatch', ['--limit' => 100]);

        $this->assertSame('sent', $event->refresh()->status);
    }


            public function supports(string $eventType): bool
            {
                return $eventType === 'fuel.flaky.v1';
            }

            public function handle(array $payload): void
            {
                $this->calls++;
                if ($this->calls === 1) {
                    throw new TransientFuelOutboxException('downstream 503');
                }
            }


    public function test_permanent_error_dead_letters_immediately(): void
    {
        [$company] = $this->seedTenant();
        $outbox = app(FuelOutboxPublisher::class);

        $outbox->publish(
            companyId: $company->id,
            eventType: 'fuel.bad.v1',
            payload: ['schema_version' => '1.0', 'event' => 'bad', 'company_id' => $company->id],
        );

        $registry = app(FuelOutboxConsumerRegistry::class);
        $registry->register(new class implements FuelOutboxConsumer
        {
            public function supports(string $eventType): bool
            {
                return $eventType === 'fuel.bad.v1';
            }

            /** @param  array<string, mixed>  $payload */
            public function handle(array $payload): void
            {
                throw new PermanentFuelOutboxException('payload invalide');
            }
        });

        Artisan::call('fuel:outbox-dispatch', ['--limit' => 100]);

        $event = FuelOutboxEvent::query()->where('event_type', 'fuel.bad.v1')->firstOrFail();
        $this->assertSame('failed', $event->status);
        $this->assertStringContainsString('payload invalide', (string) $event->last_error);
    }

            public function supports(string $eventType): bool
            {
                return $eventType === 'fuel.bad.v1';
            }

            public function handle(array $payload): void
            {
                throw new PermanentFuelOutboxException('payload invalide');
            }


    public function test_outbox_is_tenant_isolated(): void
    {
        [$companyA] = $this->seedTenant();
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $outbox = app(FuelOutboxPublisher::class);

        $outbox->publish(
            companyId: $companyA->id,
            eventType: 'fuel.cash.closed.v1',
            payload: ['schema_version' => '1.0', 'event' => 'cash.closed', 'company_id' => $companyA->id, 'idempotency_key' => 'k-a'],
        );
        $outbox->publish(
            companyId: $companyB->id,
            eventType: 'fuel.cash.closed.v1',
            payload: ['schema_version' => '1.0', 'event' => 'cash.closed', 'company_id' => $companyB->id, 'idempotency_key' => 'k-b'],
        );

        $this->assertSame(2, FuelOutboxEvent::query()->count());
        $this->assertSame(1, FuelOutboxEvent::query()->where('company_id', $companyA->id)->count());
    }

    private function seedClosedSession(): array
    {
        [$company, $manager] = $this->seedTenant();

        $session = FuelCashSession::query()->create([
            'company_id' => $company->id,
            'opened_by' => $manager->id,
            'opening_balance' => 1000.0,
            'status' => FuelCashSession::STATUS_OPEN,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/fuel-station/cash-sessions/{$session->id}/close", [
            'closing_balance' => 1500.0,
        ])->assertStatus(200);

        /** @var FuelCashSession $session */
        $session = FuelCashSession::query()->findOrFail($session->id);

        return [$company, $manager, $session];
    }

    private function seedTenant(): array
    {
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return [$company, $manager];
    }


}