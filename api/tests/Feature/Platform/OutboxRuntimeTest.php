<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Outbox\Application\Services\OutboxPublisher;
use App\Core\Outbox\Domain\Contracts\OutboxConsumer;
use App\Core\Outbox\Domain\Contracts\TenantScopedOutboxConsumer;
use App\Core\Outbox\Domain\Exceptions\PermanentOutboxException;
use App\Core\Outbox\Domain\Exceptions\TransientOutboxException;
use App\Core\Outbox\Domain\Models\OutboxEvent;
use App\Core\Outbox\Infrastructure\Services\OutboxConsumerRegistry;
use App\Core\Outbox\Infrastructure\Services\OutboxDispatcher;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\CompanyCreated;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-008 (#5866) — Runtime inbox/outbox/queues fiable (BC-01 PLATFORM).
 *
 * Prouve que l'outbox générique ne perd ni ne duplique un message sous un
 * pic de publication : déduplication par clé, lease anti double-traitement,
 * retry avec backoff, dead-letter bornée, replay contrôlé, contexte tenant
 * opt-in et observabilité.
 */
class OutboxRuntimeTest extends TestCase
{
    use RefreshTenantDatabase;

    private OutboxPublisher $publisher;

    private OutboxDispatcher $dispatcher;

    private OutboxConsumerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publisher = app(OutboxPublisher::class);
        $this->dispatcher = app(OutboxDispatcher::class);
        $this->registry = app(OutboxConsumerRegistry::class);
    }

    public function test_publish_is_deduplicated_by_idempotency_key(): void
    {
        $company = Company::factory()->create();

        $this->publisher->publish('company.created', ['company_id' => (string) $company->id], (string) $company->id, 'key-1');
        $again = $this->publisher->publish('company.created', ['company_id' => (string) $company->id], (string) $company->id, 'key-1');

        self::assertSame(1, OutboxEvent::query()->count());
        self::assertSame('pending', $again->status);
    }

    public function test_platform_publish_dedup_without_company(): void
    {
        $this->publisher->publish('platform.heartbeat', ['ok' => true], null, 'hb-1');
        $this->publisher->publish('platform.heartbeat', ['ok' => true], null, 'hb-1');

        self::assertSame(1, OutboxEvent::query()->whereNull('company_id')->count());
    }

    public function test_dispatch_delivers_platform_event_and_audits_once(): void
    {
        $company = Company::factory()->create();

        $this->publisher->publish('company.created', ['company_id' => (string) $company->id], (string) $company->id, 'company.created:'.(string) $company->id);

        $stats = $this->dispatcher->dispatch(10);

        self::assertSame(1, $stats['claimed']);
        self::assertSame(1, $stats['sent']);

        /** @var OutboxEvent $event */
        $event = OutboxEvent::query()->firstOrFail();
        self::assertSame('sent', $event->status);
        self::assertNotNull($event->processed_at);
        self::assertNull($event->lease_until);

        // Une seconde passe ne re-traite rien (déjà sent).
        $stats2 = $this->dispatcher->dispatch(10);
        self::assertSame(0, $stats2['claimed']);

        // Exactement une trace d'audit de livraison.
        self::assertSame(1, AuditLog::query()->where('action', 'outbox.delivered')->count());
    }

    public function test_transient_failure_retries_with_backoff(): void
    {
        $calls = 0;
        $consumer = $this->flakyConsumer(function () use (&$calls): void {
            $calls++;
            if ($calls === 1) {
                throw new TransientOutboxException('provider indisponible');
            }
        });
        $this->registry->register($consumer);

        $this->publisher->publish('test.flaky', ['n' => 1], null, 'flaky-1');

        $stats = $this->dispatcher->dispatch(10);
        self::assertSame(1, $stats['retried']);

        /** @var OutboxEvent $event */
        $event = OutboxEvent::query()->firstOrFail();
        self::assertSame('failed', $event->status);
        self::assertSame(1, $event->attempts);
        self::assertGreaterThan(now(), $event->available_at); // backoff futur
        self::assertStringContainsString('provider indisponible', (string) $event->last_error);

        // Échéance passée → le retry repart et réussit.
        $event->update(['available_at' => now()->subMinute()]);

        $stats2 = $this->dispatcher->dispatch(10);
        self::assertSame(1, $stats2['sent']);
        self::assertSame('sent', $event->refresh()->status);
        self::assertSame(2, $calls);
    }

    public function test_permanent_failure_goes_to_dead_letter_immediately(): void
    {
        $this->registry->register($this->failingConsumer(new PermanentOutboxException('payload invalide')));

        $this->publisher->publish('test.permanent', ['n' => 1], null, 'perm-1');

        $stats = $this->dispatcher->dispatch(10);

        self::assertSame(1, $stats['dead']);
        /** @var OutboxEvent $event */
        $event = OutboxEvent::query()->firstOrFail();
        self::assertSame('failed', $event->status);
        self::assertStringContainsString('payload invalide', (string) $event->last_error);
    }

    public function test_max_attempts_reach_dead_letter(): void
    {
        $this->registry->register($this->failingConsumer(new TransientOutboxException('boom')));
        $this->publisher->publish('test.exhaust', ['n' => 1], null, 'exh-1');

        // Chaque passe consomme une tentative (rejouer manuellement les retries).
        $dispatched = 0;
        for ($i = 0; $i < 6; $i++) {
            $stats = $this->dispatcher->dispatch(10);
            $dispatched += $stats['claimed'];
            // Faire mûrir tous les retries entre deux passes.
            OutboxEvent::query()->where('status', 'failed')->update(['available_at' => now()->subMinute()]);
        }

        /** @var OutboxEvent $event */
        $event = OutboxEvent::query()->firstOrFail();
        self::assertSame('failed', $event->status);
        self::assertSame($event->max_attempts, $event->attempts); // dead-letter atteinte
        self::assertGreaterThan(0, $dispatched);
    }

    public function test_lease_prevents_double_processing(): void
    {
        $this->registry->register($this->recordingConsumer('test.lease'));
        $this->publisher->publish('test.lease', ['n' => 1], null, 'lease-1');

        // Passe 1 : traité → sent.
        $this->dispatcher->dispatch(10);

        // Simuler un événement resté en processing dans sa lease (worker vivant).
        $processing = OutboxEvent::query()->create([
            'event_type' => 'test.lease',
            'payload' => ['n' => 2],
            'status' => 'processing',
            'idempotency_key' => 'lease-2',
            'available_at' => now(),
            'lease_until' => now()->addMinutes(15),
        ]);

        $stats = $this->dispatcher->dispatch(10);
        self::assertSame(0, $stats['claimed']); // pas de vol d'événement en lease
        self::assertSame('processing', $processing->refresh()->status);
    }

    public function test_expired_lease_is_reclaimed(): void
    {
        $calls = 0;
        $this->registry->register($this->flakyConsumer(function () use (&$calls): void {
            $calls++;
        }));

        // Événement resté en processing avec lease expirée (crash worker).
        OutboxEvent::query()->create([
            'event_type' => 'test.lease',
            'payload' => ['n' => 1],
            'status' => 'processing',
            'idempotency_key' => 'lease-3',
            'available_at' => now()->subMinute(),
            'lease_until' => now()->subMinute(),
        ]);

        $stats = $this->dispatcher->dispatch(10);

        self::assertSame(1, $stats['claimed']);
        self::assertSame(1, $calls);
        self::assertSame('sent', OutboxEvent::query()->firstOrFail()->status);
    }

    public function test_replay_command_requeues_dead_letter_and_dispatches(): void
    {
        $this->registry->register($this->recordingConsumer('test.replay'));
        $this->publisher->publish('test.replay', ['n' => 1], null, 'replay-1');

        // Forcer un événement en dead-letter.
        OutboxEvent::query()->update([
            'status' => 'failed',
            'attempts' => 5,
            'last_error' => 'dead',
        ]);

        $this->artisan('outbox:replay', ['--status' => 'dead'])->assertSuccessful();

        /** @var OutboxEvent $event */
        $event = OutboxEvent::query()->firstOrFail();
        self::assertSame('pending', $event->status);
        self::assertSame(5, $event->attempts); // budget préservé (audit)

        $stats = $this->dispatcher->dispatch(10);
        self::assertSame(1, $stats['sent']);
    }

    public function test_tenant_scoped_consumer_receives_tenant_context(): void
    {
        $seen = [];
        $consumer = new class($seen) implements TenantScopedOutboxConsumer
        {
            /** @param array<int, mixed> $seen */
            public function __construct(private array &$seen)
            {
            }

            public function supports(string $eventType): bool
            {
                return $eventType === 'test.tenant';
            }

            public function handle(OutboxEvent $event): void
            {
                $this->seen[] = app()->bound('current_company') ? app('current_company')->id : null;
            }
        };
        $this->registry->register($consumer);

        $company = Company::factory()->create();
        $this->publisher->publish('test.tenant', ['n' => 1], (string) $company->id, 'tenant-1');

        $this->dispatcher->dispatch(10);

        self::assertSame([(string) $company->id], $seen);
    }

    public function test_platform_consumer_runs_outside_tenant_context(): void
    {
        $seen = [];
        $consumer = new class($seen) implements OutboxConsumer
        {
            /** @param array<int, bool> $seen */
            public function __construct(private array &$seen)
            {
            }

            public function supports(string $eventType): bool
            {
                return $eventType === 'test.platform';
            }

            public function handle(OutboxEvent $event): void
            {
                $this->seen[] = app()->bound('current_company');
            }
        };
        $this->registry->register($consumer);

        $company = Company::factory()->create();
        $this->publisher->publish('test.platform', ['n' => 1], (string) $company->id, 'platform-1');

        $this->dispatcher->dispatch(10);

        self::assertSame([false], $seen);
    }

    public function test_metrics_command_and_dispatcher_report_counts(): void
    {
        $this->publisher->publish('company.created', ['n' => 1], null, 'm-1');
        $this->publisher->publish('company.created', ['n' => 2], null, 'm-2');

        $metrics = $this->dispatcher->metrics();
        self::assertSame(2, $metrics['statuses']['pending'] ?? 0);
        self::assertFalse($metrics['backpressure']);

        $this->artisan('outbox:metrics')->assertSuccessful();
    }

    public function test_company_created_event_flows_through_outbox_exactly_once(): void
    {
        // Pic simulé : l'événement est publié deux fois (re-dispatch métier),
        // l'outbox doit dédupliquer et livrer exactement une fois.
        $company = Company::factory()->create();

        CompanyCreated::dispatch($company);
        CompanyCreated::dispatch($company);

        self::assertSame(1, OutboxEvent::query()->where('event_type', 'company.created')->count());

        $this->dispatcher->dispatch(10);
        $this->dispatcher->dispatch(10);

        self::assertSame(1, AuditLog::query()->where('action', 'outbox.delivered')->count());
        self::assertSame('sent', OutboxEvent::query()->firstOrFail()->status);
    }

    private function recordingConsumer(string $eventType): OutboxConsumer
    {
        return new class($eventType) implements OutboxConsumer
        {
            public function __construct(private readonly string $eventType)
            {
            }

            public function supports(string $eventType): bool
            {
                return $eventType === $this->eventType;
            }

            public function handle(OutboxEvent $event): void
            {
            }
        };
    }

    private function flakyConsumer(callable $handler): OutboxConsumer
    {
        return new class($handler) implements OutboxConsumer
        {
            /** @var callable */
            private $handler;

            public function __construct(callable $handler)
            {
                $this->handler = $handler;
            }

            public function supports(string $eventType): bool
            {
                return $eventType === 'test.flaky' || $eventType === 'test.lease';
            }

            public function handle(OutboxEvent $event): void
            {
                ($this->handler)();
            }
        };
    }

    private function failingConsumer(\Throwable $exception): OutboxConsumer
    {
        return new class($exception) implements OutboxConsumer
        {
            public function __construct(private readonly \Throwable $exception)
            {
            }

            public function supports(string $eventType): bool
            {
                return str_starts_with($eventType, 'test.');
            }

            public function handle(OutboxEvent $event): void
            {
                throw $this->exception;
            }
        };
    }
}
