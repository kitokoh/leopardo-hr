<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Contracts\FuelOutboxConsumer;
use App\Modules\FuelStation\Domain\Exceptions\PermanentFuelOutboxException;
use App\Modules\FuelStation\Domain\Exceptions\TransientFuelOutboxException;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Infrastructure\Services\FuelOutboxConsumerRegistry;
use App\Modules\FuelStation\Infrastructure\Services\FuelOutboxPublisher;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Contrat Accounting FuelStation — FUEL-015 (issue #5809).
 *
 * Couvre : publication de l'agrégat de clôture de caisse dans l'outbox,
 * idempotence (clé unique par tenant), dispatch avec consommation
 * (audit idempotent), retry avec backoff sur erreur transitoire,
 * dead-letter sur erreur permanente / attempts max, isolation tenant.
 */
class FuelAccountingOutboxTest extends TestCase
{
    use RefreshTenantDatabase;
    use WithFaker;

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

    /**
     * @return array{0: Company, 1: Employee, 2: FuelCashSession}
     */
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

    /**
     * @return array{0: Company, 1: Employee}
     */
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
